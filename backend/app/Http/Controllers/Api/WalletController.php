<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\FundWalletRequest;
use App\Http\Requests\WithdrawWalletRequest;
use App\Models\BankAccount;
use App\Models\Withdrawal;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalletController extends ApiController
{
    public function fund(FundWalletRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $amount = $request->input('amount');
            $paymentMethod = $request->input('payment_method', 'card');
            $reference = 'WALLET-' . strtoupper(uniqid());

            // Development mode: Skip Paystack and directly credit wallet
            if (config('app.env') === 'development' || config('app.env') === 'local') {
                Log::info('Development mode: Simulating wallet funding', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                ]);

                // Create pending transaction
                $transaction = WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'balance_before' => $user->wallet_balance,
                    'balance_after' => $user->wallet_balance,
                    'purpose' => 'Wallet funding (Development)',
                    'reference' => $reference,
                    'status' => 'pending',
                    'metadata' => [
                        'payment_method' => $paymentMethod,
                        'initiated_at' => now()->toDateTimeString(),
                        'development_mode' => true,
                    ],
                ]);

                // Immediately credit the wallet in development mode
                DB::transaction(function () use ($user, $amount, $transaction) {
                    $user->increment('wallet_balance', $amount);
                    $transaction->update([
                        'status' => 'successful',
                        'balance_after' => $user->fresh()->wallet_balance,
                        'metadata' => array_merge($transaction->metadata, [
                            'completed_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                });

                // Invalidate wallet balance cache after successful funding
                \Illuminate\Support\Facades\Cache::forget("wallet_balance_{$user->id}");

                return $this->successResponse([
                    'reference' => $reference,
                    'amount' => $amount,
                    'new_balance' => $user->fresh()->wallet_balance,
                    'development_mode' => true,
                    'message' => 'Wallet funded successfully (Development Mode)',
                ], 'Wallet funded successfully');
            }

            // Production mode: Use Paystack
            $transaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $user->wallet_balance,
                'balance_after' => $user->wallet_balance,
                'purpose' => 'Wallet funding',
                'reference' => $reference,
                'status' => 'pending',
                'metadata' => [
                    'payment_method' => $paymentMethod,
                    'initiated_at' => now()->toDateTimeString(),
                ],
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.paystack.secret_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $amount * 100,
                'reference' => $reference,
                'callback_url' => config('app.url') . '/api/v1/wallet/verify',
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'wallet_funding',
                    'transaction_id' => $transaction->id,
                ],
            ]);

            if (!$response->successful()) {
                Log::error('Paystack initialization failed', [
                    'response' => $response->json(),
                    'user_id' => $user->id,
                    'amount' => $amount,
                ]);
                return $this->errorResponse('Failed to initialize payment. Please try again.', 500);
            }

            $data = $response->json('data');
            return $this->successResponse([
                'authorization_url' => $data['authorization_url'],
                'access_code' => $data['access_code'],
                'reference' => $reference,
            ], 'Payment initialized successfully');

        } catch (\Exception $e) {
            Log::error('Wallet funding error', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return $this->errorResponse('An error occurred while processing your request', 500);
        }
    }

    public function withdraw(WithdrawWalletRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $amount = $request->input('amount');
            $bankAccountId = $request->input('bank_account_id');

            $bankAccount = BankAccount::where('id', $bankAccountId)->where('user_id', $user->id)->first();
            if (!$bankAccount) {
                return $this->errorResponse('Bank account not found or does not belong to you', 400);
            }

            if ($user->wallet_balance < $amount) {
                return $this->errorResponse("Insufficient wallet balance. Available: {$user->wallet_balance}, Required: {$amount}", 400);
            }

            // Fraud detection check for withdrawal
            $fraudService = app(\App\Services\FraudDetectionService::class);
            $fraudResult = $fraudService->analyzeWithdrawal(
                $user->id,
                $amount,
                $bankAccount->account_number
            );

            // Log fraud check result
            Log::info('Withdrawal fraud check', [
                'user_id' => $user->id,
                'amount' => $amount,
                'risk_score' => $fraudResult['risk_score'] ?? 0,
                'recommendation' => $fraudResult['recommendation'] ?? 'approve'
            ]);

            // Handle high-risk withdrawals
            if (($fraudResult['recommendation'] ?? 'approve') === 'suspend') {
                $fraudService->handleFraudResult($user->id, $fraudResult);
                return $this->errorResponse('Withdrawal blocked due to security concerns. Please contact support.', 403);
            }

            DB::beginTransaction();
            try {
                $user->lockForUpdate();
                $balanceBefore = $user->wallet_balance;
                $user->wallet_balance -= $amount;
                $user->save();

                $transactionReference = 'WD-' . strtoupper(uniqid());
                $transaction = WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $user->wallet_balance,
                    'purpose' => 'Wallet withdrawal',
                    'reference' => $transactionReference,
                    'status' => 'successful',
                    'metadata' => [
                        'bank_account_id' => $bankAccount->id,
                        'bank_name' => $bankAccount->bank_name,
                        'account_number' => $bankAccount->account_number,
                        'fraud_check' => [
                            'risk_score' => $fraudResult['risk_score'] ?? 0,
                            'recommendation' => $fraudResult['recommendation'] ?? 'approve'
                        ]
                    ],
                ]);

                $withdrawalReference = 'WDRL-' . strtoupper(uniqid());
                $withdrawal = Withdrawal::create([
                    'user_id' => $user->id,
                    'bank_account_id' => $bankAccount->id,
                    'amount' => $amount,
                    'status' => 'pending',
                    'admin_approval_status' => 'pending',
                    'payment_reference' => $withdrawalReference,
                ]);

                // Flag for review if medium risk
                if (($fraudResult['recommendation'] ?? 'approve') === 'review') {
                    $fraudService->handleFraudResult($user->id, $fraudResult);
                }

                DB::commit();

                // Invalidate wallet balance cache after successful withdrawal
                \Illuminate\Support\Facades\Cache::forget("wallet_balance_{$user->id}");

                return $this->successResponse([
                    'id' => $withdrawal->id,
                    'user_id' => $withdrawal->user_id,
                    'bank_account_id' => $withdrawal->bank_account_id,
                    'amount' => $withdrawal->amount,
                    'status' => $withdrawal->status,
                    'admin_approval_status' => $withdrawal->admin_approval_status,
                    'payment_reference' => $withdrawal->payment_reference,
                    'bank_account' => [
                        'id' => $bankAccount->id,
                        'account_name' => $bankAccount->account_name,
                        'account_number' => $bankAccount->account_number,
                        'bank_name' => $bankAccount->bank_name,
                    ],
                    'created_at' => $withdrawal->created_at,
                    'updated_at' => $withdrawal->updated_at,
                ], 'Withdrawal initiated successfully', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Wallet withdrawal error', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return $this->errorResponse('An error occurred while processing your withdrawal', 500);
        }
    }

    public function getBalance(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cacheKey = "wallet_balance_{$user->id}";
            
            // Check if force refresh is requested
            $forceRefresh = $request->query('force_refresh') || $request->query('no_cache');
            
            if ($forceRefresh) {
                // Skip cache and fetch fresh data from database
                $balance = (float) $user->fresh()->wallet_balance;
            } else {
                // Cache balance for 5 minutes (300 seconds)
                $balance = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user) {
                    return (float) $user->fresh()->wallet_balance;
                });
            }
            
            return $this->successResponse([
                'balance' => $balance,
                'currency' => 'NGN'
            ]);
        } catch (\Exception $e) {
            Log::error('Get balance error', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return $this->errorResponse('An error occurred while fetching your balance', 500);
        }
    }

    public function getTransactions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
            $paginated = WalletTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Cast decimal fields to float for each transaction
            $transactions = $paginated->items();
            $transactionsData = array_map(function($transaction) {
                $data = $transaction->toArray();
                $data['amount'] = (float) $transaction->amount;
                $data['balance_before'] = (float) $transaction->balance_before;
                $data['balance_after'] = (float) $transaction->balance_after;
                return $data;
            }, $transactions);

            return $this->successResponse([
                'transactions' => $transactionsData,
                'pagination' => [
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get transactions error', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return $this->errorResponse('An error occurred while fetching transactions', 500);
        }
    }

    public function getTransaction(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $transaction = WalletTransaction::where('id', $id)->where('user_id', $user->id)->first();
            if (!$transaction) {
                return $this->errorResponse('Transaction not found', 404);
            }
            
            // Cast decimal fields to float for JSON response
            $transactionData = $transaction->toArray();
            $transactionData['amount'] = (float) $transaction->amount;
            $transactionData['balance_before'] = (float) $transaction->balance_before;
            $transactionData['balance_after'] = (float) $transaction->balance_after;
            
            return $this->successResponse($transactionData);
        } catch (\Exception $e) {
            Log::error('Get transaction error', ['error' => $e->getMessage(), 'user_id' => $request->user()->id, 'transaction_id' => $id]);
            return $this->errorResponse('An error occurred while fetching transaction details', 500);
        }
    }

    /**
     * Get list of Nigerian banks for bank account linking
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getBanks(Request $request): JsonResponse
    {
        try {
            // List of major Nigerian banks with their codes
            $banks = [
                ['code' => '044', 'name' => 'Access Bank'],
                ['code' => '063', 'name' => 'Access Bank (Diamond)'],
                ['code' => '050', 'name' => 'Ecobank Nigeria'],
                ['code' => '070', 'name' => 'Fidelity Bank'],
                ['code' => '011', 'name' => 'First Bank of Nigeria'],
                ['code' => '214', 'name' => 'First City Monument Bank'],
                ['code' => '058', 'name' => 'Guaranty Trust Bank'],
                ['code' => '030', 'name' => 'Heritage Bank'],
                ['code' => '301', 'name' => 'Jaiz Bank'],
                ['code' => '082', 'name' => 'Keystone Bank'],
                ['code' => '526', 'name' => 'Parallex Bank'],
                ['code' => '076', 'name' => 'Polaris Bank'],
                ['code' => '101', 'name' => 'Providus Bank'],
                ['code' => '221', 'name' => 'Stanbic IBTC Bank'],
                ['code' => '068', 'name' => 'Standard Chartered Bank'],
                ['code' => '232', 'name' => 'Sterling Bank'],
                ['code' => '100', 'name' => 'Suntrust Bank'],
                ['code' => '032', 'name' => 'Union Bank of Nigeria'],
                ['code' => '033', 'name' => 'United Bank for Africa'],
                ['code' => '215', 'name' => 'Unity Bank'],
                ['code' => '035', 'name' => 'Wema Bank'],
                ['code' => '057', 'name' => 'Zenith Bank'],
            ];

            return $this->successResponse($banks, 'Banks retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Get banks error', ['error' => $e->getMessage()]);
            return $this->errorResponse('An error occurred while fetching banks list', 500);
        }
    }
}
