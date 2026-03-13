<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreContributionRequest;
use App\Http\Requests\VerifyContributionRequest;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ContributionController extends ApiController
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    #[OA\Post(
        path: '/api/v1/contributions',
        summary: 'Record a contribution to a group',
        security: [['bearerAuth' => []]],
        tags: ['Contributions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['group_id', 'payment_method'],
                properties: [
                    new OA\Property(property: 'group_id', type: 'integer', example: 1),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['wallet', 'card', 'bank_transfer'], example: 'wallet'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Contribution recorded successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Contribution recorded successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'group_id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'amount', type: 'number', example: 1000.00),
                                new OA\Property(property: 'payment_method', type: 'string', example: 'wallet'),
                                new OA\Property(property: 'payment_reference', type: 'string', example: 'CONT-20240115-ABC123'),
                                new OA\Property(property: 'payment_status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'contribution_date', type: 'string', format: 'date', example: '2024-01-15'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'User is not an active member of the group'),
            new OA\Response(response: 422, description: 'Validation error or business rule violation'),
        ]
    )]
    public function store(StoreContributionRequest $request)
    {
        try {
            $user = $request->user();
            $groupId = $request->group_id;
            $paymentMethod = $request->payment_method;

            $contribution = DB::transaction(function () use ($user, $groupId, $paymentMethod) {
                // Lock the group for reading to ensure consistent state
                $group = Group::where('id', $groupId)->lockForUpdate()->first();

                if (!$group) {
                    throw new \Exception('Group not found', 404);
                }

                // Validate group is in 'active' status
                if (!$group->isActive()) {
                    throw new \Exception('Cannot contribute to this group. Group is not active.', 422);
                }

                // Verify user is an active member of the group
                $membership = GroupMember::where('group_id', $groupId)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->first();

                if (!$membership) {
                    throw new \Exception('You are not an active member of this group', 403);
                }

                // Check for duplicate contribution on the same date
                $today = now()->toDateString();
                $existingContribution = Contribution::where('group_id', $groupId)
                    ->where('user_id', $user->id)
                    ->where('contribution_date', $today)
                    ->first();

                if ($existingContribution) {
                    throw new \Exception('You have already contributed to this group today', 422);
                }

                // Get contribution amount from group
                $amount = $group->contribution_amount;

                // Fraud detection check for payment
                $fraudService = app(\App\Services\FraudDetectionService::class);
                $fraudResult = $fraudService->analyzePayment(
                    $user->id,
                    $amount,
                    $paymentMethod,
                    [
                        'group_id' => $groupId,
                        'group_name' => $group->name,
                        'contribution_date' => $today
                    ]
                );

                // Log fraud check result
                Log::info('Payment fraud check', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'risk_score' => $fraudResult['risk_score'] ?? 0,
                    'recommendation' => $fraudResult['recommendation'] ?? 'approve'
                ]);

                // Handle high-risk payments
                if (($fraudResult['recommendation'] ?? 'approve') === 'suspend') {
                    $fraudService->handleFraudResult($user->id, $fraudResult);
                    throw new \Exception('Payment blocked due to security concerns. Please contact support.', 403);
                }

                // Generate unique payment reference
                $paymentReference = $this->generatePaymentReference();

                // Handle wallet payment
                if ($paymentMethod === 'wallet') {
                    // Lock user for update to prevent race conditions
                    $userModel = User::where('id', $user->id)->lockForUpdate()->first();

                    // Use WalletService to debit wallet
                    try {
                        $this->walletService->debitWallet(
                            $userModel,
                            $amount,
                            "Contribution to group: {$group->name}",
                            [
                                'group_id' => $groupId,
                                'group_name' => $group->name,
                                'contribution_date' => $today,
                                'fraud_check' => [
                                    'risk_score' => $fraudResult['risk_score'] ?? 0,
                                    'recommendation' => $fraudResult['recommendation'] ?? 'approve'
                                ]
                            ],
                            $paymentReference  // Pass the payment reference
                        );
                    } catch (\Exception $e) {
                        throw new \Exception($e->getMessage(), 422);
                    }

                    // Flag for review if medium risk
                    if (($fraudResult['recommendation'] ?? 'approve') === 'review') {
                        $fraudService->handleFraudResult($user->id, $fraudResult);
                    }

                    // Create contribution record with 'successful' status for wallet payments
                    $contribution = Contribution::create([
                        'group_id' => $groupId,
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference,
                        'payment_status' => 'successful',
                        'contribution_date' => $today,
                        'paid_at' => now(),
                    ]);
                } else {
                    // For card/bank_transfer, create contribution with 'pending' status
                    // Payment gateway integration will be handled in webhook
                    $contribution = Contribution::create([
                        'group_id' => $groupId,
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference,
                        'payment_status' => 'pending',
                        'contribution_date' => $today,
                    ]);

                    // TODO: Initiate payment gateway transaction
                    // This will be implemented in the payment gateway integration task
                }

                return $contribution;
            });

            return $this->successResponse(
                $contribution->fresh(),
                'Contribution recorded successfully',
                201
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate key constraint violation
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                return $this->errorResponse(
                    'You have already contributed to this group today',
                    422
                );
            }

            return $this->errorResponse(
                'Database error: ' . $e->getMessage(),
                500
            );
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;

            // Ensure status code is valid HTTP status code
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = 500;
            }

            return $this->errorResponse(
                $e->getMessage(),
                $statusCode
            );
        }
    }

    #[OA\Get(
        path: '/api/v1/contributions',
        summary: 'Get user contribution history',
        security: [['bearerAuth' => []]],
        tags: ['Contributions'],
        parameters: [
            new OA\Parameter(name: 'group_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'payment_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'successful', 'failed'])),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User contribution history retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Contribution history retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'total', type: 'integer', example: 50),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $user = $request->user();
            $perPage = $request->input('per_page', 15);

            // Build query
            $query = Contribution::with(['group:id,name,contribution_amount,status'])
                ->where('user_id', $user->id);

            // Apply filters
            if ($request->has('group_id')) {
                $query->where('group_id', $request->group_id);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('contribution_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('contribution_date', '<=', $request->date_to);
            }

            // Order by contribution date descending
            $query->orderBy('contribution_date', 'desc');

            // Paginate results
            $contributions = $query->paginate($perPage);

            return $this->successResponse(
                $contributions,
                'Contribution history retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve contribution history: ' . $e->getMessage(),
                500
            );
        }
    }

    #[OA\Get(
        path: '/api/v1/groups/{groupId}/contributions',
        summary: 'Get contributions for a specific group',
        security: [['bearerAuth' => []]],
        tags: ['Contributions'],
        parameters: [
            new OA\Parameter(name: 'groupId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'payment_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'successful', 'failed'])),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group contributions retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Group contributions retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'total', type: 'integer', example: 100),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'User is not a member of the group'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function groupContributions(\Illuminate\Http\Request $request, int $groupId)
    {
        try {
            $user = $request->user();
            $perPage = $request->input('per_page', 15);

            // Verify group exists
            $group = Group::find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Verify user is a member of the group
            $membership = GroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->first();

            if (!$membership) {
                return $this->errorResponse(
                    'You are not a member of this group',
                    403
                );
            }

            // Build query
            $query = Contribution::with(['user:id,name,email'])
                ->where('group_id', $groupId);

            // Apply filters
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('contribution_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('contribution_date', '<=', $request->date_to);
            }

            // Order by contribution date descending
            $query->orderBy('contribution_date', 'desc');

            // Paginate results
            $contributions = $query->paginate($perPage);

            return $this->successResponse(
                $contributions,
                'Group contributions retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve group contributions: ' . $e->getMessage(),
                500
            );
        }
    }

    #[OA\Get(
        path: '/api/v1/contributions/missed',
        summary: 'Get missed contributions for the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Contributions'],
        parameters: [
            new OA\Parameter(name: 'group_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Missed contributions retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Missed contributions retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'missed_contributions', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'total_missed_amount', type: 'number', example: 5000.00),
                                new OA\Property(property: 'total_missed_count', type: 'integer', example: 5),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function missed(\Illuminate\Http\Request $request)
    {
        try {
            $user = $request->user();

            // Get all active groups where user is a member
            $query = GroupMember::with(['group'])
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereHas('group', function ($q) {
                    $q->where('status', 'active');
                });

            // Apply group filter if provided
            if ($request->has('group_id')) {
                $query->where('group_id', $request->group_id);
            }

            $memberships = $query->get();

            $missedContributions = [];
            $totalMissedAmount = 0;

            foreach ($memberships as $membership) {
                $group = $membership->group;
                $startDate = $group->start_date;
                $today = now()->toDateString();

                // Calculate expected contribution dates based on frequency
                $expectedDates = [];
                $currentDate = $startDate->copy();

                while ($currentDate->lte(now()->startOfDay())) {
                    $expectedDates[] = $currentDate->toDateString();

                    // Increment based on frequency
                    if ($group->frequency === 'weekly') {
                        $currentDate->addWeek();
                    } else {
                        // Default to daily
                        $currentDate->addDay();
                    }
                }

                // Get all successful contributions for this group
                $successfulContributions = Contribution::where('group_id', $group->id)
                    ->where('user_id', $user->id)
                    ->where('payment_status', 'successful')
                    ->pluck('contribution_date')
                    ->map(fn($date) => $date->toDateString())
                    ->toArray();

                // Find missed dates
                $missedDates = array_diff($expectedDates, $successfulContributions);

                // Add to missed contributions array
                foreach ($missedDates as $missedDate) {
                    $missedContributions[] = [
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'contribution_amount' => $group->contribution_amount,
                        'missed_date' => $missedDate,
                        'frequency' => $group->frequency,
                    ];

                    $totalMissedAmount += $group->contribution_amount;
                }
            }

            // Sort by missed date descending
            usort($missedContributions, function ($a, $b) {
                return strcmp($b['missed_date'], $a['missed_date']);
            });

            return $this->successResponse(
                [
                    'missed_contributions' => $missedContributions,
                    'total_missed_amount' => $totalMissedAmount,
                    'total_missed_count' => count($missedContributions),
                ],
                'Missed contributions retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve missed contributions: ' . $e->getMessage(),
                500
            );
        }
    }

    #[OA\Post(
        path: '/api/v1/contributions/verify',
        summary: 'Verify a contribution payment with payment gateway',
        security: [['bearerAuth' => []]],
        tags: ['Contributions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['payment_reference'],
                properties: [
                    new OA\Property(property: 'payment_reference', type: 'string', example: 'CONT-20240315-ABC12345'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contribution verified successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Contribution verified successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'payment_status', type: 'string', example: 'successful'),
                                new OA\Property(property: 'amount', type: 'number', example: 1000.00),
                                new OA\Property(property: 'paid_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Contribution does not belong to user'),
            new OA\Response(response: 404, description: 'Contribution not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 502, description: 'Payment gateway error'),
        ]
    )]
    public function verify(VerifyContributionRequest $request)
    {
        try {
            $user = $request->user();
            $paymentReference = $request->payment_reference;

            return DB::transaction(function () use ($user, $paymentReference) {
                // Find contribution by payment reference
                $contribution = Contribution::where('payment_reference', $paymentReference)
                    ->lockForUpdate()
                    ->first();

                if (!$contribution) {
                    throw new \Exception('Contribution not found with the provided payment reference', 404);
                }

                // Verify contribution belongs to authenticated user
                if ($contribution->user_id !== $user->id) {
                    throw new \Exception('You are not authorized to verify this contribution', 403);
                }

                // If already successful, return idempotent response
                if ($contribution->isSuccessful()) {
                    Log::info('Contribution already verified as successful', [
                        'contribution_id' => $contribution->id,
                        'reference' => $paymentReference,
                        'user_id' => $user->id,
                    ]);

                    return $this->successResponse(
                        $contribution->fresh(),
                        'Contribution already verified as successful'
                    );
                }

                // Call Paystack API to verify transaction
                $gatewayResponse = $this->verifyPaymentWithGateway($paymentReference);

                // Process based on gateway response
                $gatewayStatus = $gatewayResponse['status'] ?? null;

                if ($gatewayStatus === 'success') {
                    // Update contribution to successful
                    $contribution->update([
                        'payment_status' => 'successful',
                        'paid_at' => now(),
                    ]);

                    Log::info('Contribution verified and marked as successful', [
                        'contribution_id' => $contribution->id,
                        'reference' => $paymentReference,
                        'user_id' => $user->id,
                    ]);

                    // Credit wallet if payment method was card or bank_transfer
                    // and wallet hasn't been credited yet
                    if (in_array($contribution->payment_method, ['card', 'bank_transfer'])) {
                        $this->creditWalletIfNeeded($contribution, $gatewayResponse);
                    }

                    return $this->successResponse(
                        $contribution->fresh(),
                        'Contribution verified successfully'
                    );
                } elseif ($gatewayStatus === 'failed') {
                    // Update contribution to failed
                    $contribution->update([
                        'payment_status' => 'failed',
                    ]);

                    Log::info('Contribution verified and marked as failed', [
                        'contribution_id' => $contribution->id,
                        'reference' => $paymentReference,
                        'user_id' => $user->id,
                        'gateway_response' => $gatewayResponse['message'] ?? 'Unknown',
                    ]);

                    return $this->successResponse(
                        $contribution->fresh(),
                        'Payment verification failed: ' . ($gatewayResponse['message'] ?? 'Payment was not successful')
                    );
                } else {
                    // Payment still pending
                    Log::info('Contribution still pending', [
                        'contribution_id' => $contribution->id,
                        'reference' => $paymentReference,
                        'user_id' => $user->id,
                        'gateway_status' => $gatewayStatus,
                    ]);

                    return $this->successResponse(
                        $contribution->fresh(),
                        'Payment is still pending. Please try again later.'
                    );
                }
            });
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Payment gateway API error', [
                'error' => $e->getMessage(),
                'reference' => $request->payment_reference,
            ]);

            return $this->errorResponse(
                'Unable to verify payment with gateway. Please try again later.',
                502
            );
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;

            // Ensure status code is valid HTTP status code
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = 500;
            }

            Log::error('Contribution verification error', [
                'error' => $e->getMessage(),
                'reference' => $request->payment_reference,
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                $e->getMessage(),
                $statusCode
            );
        }
    }

    /**
     * Verify payment with Paystack gateway.
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    private function verifyPaymentWithGateway(string $reference): array
    {
        $secretKey = config('services.paystack.secret_key');

        if (!$secretKey) {
            throw new \Exception('Paystack secret key not configured', 500);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type' => 'application/json',
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");

        if (!$response->successful()) {
            Log::error('Paystack API error', [
                'reference' => $reference,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Illuminate\Http\Client\RequestException($response);
        }

        $data = $response->json();

        if (!isset($data['status']) || !$data['status']) {
            throw new \Exception('Payment verification failed: ' . ($data['message'] ?? 'Unknown error'), 502);
        }

        return $data['data'] ?? [];
    }

    /**
     * Credit user's wallet if not already credited.
     */
    private function creditWalletIfNeeded(Contribution $contribution, array $gatewayResponse): void
    {
        // Check if wallet has already been credited for this contribution
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
                    'gateway_response' => $gatewayResponse,
                ],
                $contribution->payment_reference  // Pass the payment reference
            );

            Log::info('User wallet credited via verification', [
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
     * Generate a unique payment reference for the contribution.
     */
    private function generatePaymentReference(): string
    {
        do {
            $reference = 'CONT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
            $exists = Contribution::where('payment_reference', $reference)->exists();
        } while ($exists);

        return $reference;
    }
}
