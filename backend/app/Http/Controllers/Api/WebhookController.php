<?php

namespace App\Http\Controllers\Api;

use App\Models\Contribution;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class WebhookController extends ApiController
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    #[OA\Post(
        path: '/api/v1/webhooks/paystack',
        summary: 'Handle Paystack payment webhook',
        tags: ['Webhooks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'event', type: 'string', example: 'charge.success'),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'reference', type: 'string', example: 'CONT-20240315-ABC12345'),
                            new OA\Property(property: 'amount', type: 'integer', example: 100000),
                            new OA\Property(property: 'status', type: 'string', example: 'success'),
                            new OA\Property(property: 'paid_at', type: 'string', example: '2024-03-15T10:30:00.000Z'),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Webhook processed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Webhook processed successfully'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid signature or payload'),
        ]
    )]
    public function handlePaystackWebhook(Request $request)
    {
        try {
            // Verify webhook signature
            if (!$this->verifyPaystackSignature($request)) {
                Log::warning('Invalid Paystack webhook signature', [
                    'ip' => $request->ip(),
                    'payload' => $request->all(),
                ]);

                return $this->errorResponse('Invalid webhook signature', 400);
            }

            $payload = $request->all();
            $event = $payload['event'] ?? null;
            $data = $payload['data'] ?? [];

            // Extract payment details
            $reference = $data['reference'] ?? null;
            $status = $data['status'] ?? null;
            $amount = isset($data['amount']) ? $data['amount'] / 100 : null; // Paystack sends amount in kobo

            if (!$reference) {
                Log::warning('Webhook missing payment reference', ['payload' => $payload]);
                return $this->errorResponse('Missing payment reference', 400);
            }

            // Implement idempotency check
            $cacheKey = "webhook_processed_{$reference}";
            if (Cache::has($cacheKey)) {
                Log::info('Webhook already processed (idempotency check)', [
                    'reference' => $reference,
                    'event' => $event,
                ]);

                return $this->successResponse(null, 'Webhook already processed');
            }

            // Process the webhook based on event type
            if ($event === 'charge.success' && $status === 'success') {
                $this->processSuccessfulPayment($reference, $amount, $data);
            } elseif ($event === 'charge.failed' || $status === 'failed') {
                $this->processFailedPayment($reference, $data);
            }

            // Mark webhook as processed (cache for 24 hours)
            Cache::put($cacheKey, true, now()->addHours(24));

            Log::info('Webhook processed successfully', [
                'reference' => $reference,
                'event' => $event,
                'status' => $status,
            ]);

            return $this->successResponse(null, 'Webhook processed successfully');
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return $this->errorResponse('Webhook processing failed', 500);
        }
    }

    /**
     * Verify Paystack webhook signature using HMAC SHA512.
     */
    private function verifyPaystackSignature(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');

        if (!$signature) {
            return false;
        }

        $secretKey = config('services.paystack.secret_key');

        if (!$secretKey) {
            Log::error('Paystack secret key not configured');
            return false;
        }

        $body = $request->getContent();
        $computedSignature = hash_hmac('sha512', $body, $secretKey);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Process successful payment webhook.
     */
    private function processSuccessfulPayment(string $reference, ?float $amount, array $data): void
    {
        DB::transaction(function () use ($reference, $amount, $data) {
            // Check if this is a wallet funding transaction
            if (str_starts_with($reference, 'WALLET-')) {
                $this->processWalletFunding($reference, $amount, $data);
                return;
            }

            // Find contribution by payment reference
            $contribution = Contribution::where('payment_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$contribution) {
                Log::warning('Contribution not found for payment reference', [
                    'reference' => $reference,
                ]);
                return;
            }

            // Check if already processed
            if ($contribution->isSuccessful()) {
                Log::info('Contribution already marked as successful', [
                    'reference' => $reference,
                    'contribution_id' => $contribution->id,
                ]);
                return;
            }

            // Update contribution status
            $contribution->update([
                'payment_status' => 'successful',
                'paid_at' => now(),
            ]);

            Log::info('Contribution marked as successful', [
                'contribution_id' => $contribution->id,
                'reference' => $reference,
                'amount' => $contribution->amount,
            ]);

            // Credit wallet if payment method was card or bank_transfer
            if (in_array($contribution->payment_method, ['card', 'bank_transfer'])) {
                $this->creditUserWallet($contribution, $data);
            }
        });
    }

    /**
     * Process failed payment webhook.
     */
    private function processFailedPayment(string $reference, array $data): void
    {
        DB::transaction(function () use ($reference, $data) {
            // Find contribution by payment reference
            $contribution = Contribution::where('payment_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$contribution) {
                Log::warning('Contribution not found for payment reference', [
                    'reference' => $reference,
                ]);
                return;
            }

            // Check if already processed as failed
            if ($contribution->isFailed()) {
                Log::info('Contribution already marked as failed', [
                    'reference' => $reference,
                    'contribution_id' => $contribution->id,
                ]);
                return;
            }

            // Update contribution status to failed
            $contribution->update([
                'payment_status' => 'failed',
            ]);

            Log::info('Contribution marked as failed', [
                'contribution_id' => $contribution->id,
                'reference' => $reference,
                'reason' => $data['gateway_response'] ?? 'Unknown',
            ]);
        });
    }

    /**
     * Credit user's wallet for card/bank_transfer payments.
     */
    private function creditUserWallet(Contribution $contribution, array $data): void
    {
        // Check if wallet has already been credited
        $existingTransaction = WalletTransaction::where('reference', $contribution->payment_reference)
            ->where('user_id', $contribution->user_id)
            ->where('type', 'credit')
            ->first();

        if ($existingTransaction) {
            Log::info('Wallet already credited for this contribution', [
                'contribution_id' => $contribution->id,
                'transaction_id' => $existingTransaction->id,
            ]);
            return;
        }

        // Use WalletService to credit wallet
        try {
            $user = User::find($contribution->user_id);

            if (!$user) {
                Log::error('User not found for wallet credit', [
                    'user_id' => $contribution->user_id,
                    'contribution_id' => $contribution->id,
                ]);
                return;
            }

            $this->walletService->creditWallet(
                $user,
                $contribution->amount,
                "Payment received for contribution to group: {$contribution->group->name}",
                [
                    'contribution_id' => $contribution->id,
                    'group_id' => $contribution->group_id,
                    'payment_method' => $contribution->payment_method,
                    'payment_data' => $data,
                ],
                $contribution->payment_reference  // Pass the payment reference
            );

            Log::info('User wallet credited', [
                'user_id' => $user->id,
                'amount' => $contribution->amount,
                'contribution_id' => $contribution->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to credit wallet', [
                'user_id' => $contribution->user_id,
                'contribution_id' => $contribution->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process wallet funding webhook.
     */
    private function processWalletFunding(string $reference, ?float $amount, array $data): void
    {
        // Find wallet transaction by reference
        $transaction = WalletTransaction::where('reference', $reference)
            ->lockForUpdate()
            ->first();

        if (!$transaction) {
            Log::warning('Wallet transaction not found for payment reference', [
                'reference' => $reference,
            ]);
            return;
        }

        // Check if already processed
        if ($transaction->status === 'successful') {
            Log::info('Wallet transaction already marked as successful', [
                'reference' => $reference,
                'transaction_id' => $transaction->id,
            ]);
            return;
        }

        // Use WalletService to credit the wallet
        try {
            $user = User::find($transaction->user_id);

            if (!$user) {
                Log::error('User not found for wallet funding', [
                    'user_id' => $transaction->user_id,
                    'transaction_id' => $transaction->id,
                ]);

                // Mark transaction as failed
                $transaction->update([
                    'status' => 'failed',
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'error' => 'User not found',
                    ]),
                ]);
                return;
            }

            // Get balance before crediting
            $balanceBefore = $user->wallet_balance;

            // Credit the wallet directly (not using WalletService to avoid duplicate transaction)
            $user->increment('wallet_balance', $transaction->amount);

            // Update the pending transaction to successful
            $transaction->update([
                'status' => 'successful',
                'balance_before' => $balanceBefore,
                'balance_after' => $user->fresh()->wallet_balance,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'payment_data' => $data,
                    'completed_at' => now()->toIso8601String(),
                ]),
            ]);

            Log::info('Wallet funded successfully', [
                'user_id' => $user->id,
                'amount' => $transaction->amount,
                'reference' => $reference,
                'transaction_id' => $transaction->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process wallet funding', [
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            // Mark transaction as failed
            $transaction->update([
                'status' => 'failed',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'error' => $e->getMessage(),
                ]),
            ]);
        }
    }
}
