<?php

namespace App\Http\Controllers\Api;

use App\Models\Payout;
use App\Models\Group;
use App\Services\PayoutService;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class PayoutController extends ApiController
{
    protected PayoutService $payoutService;

    public function __construct(PayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    #[OA\Get(
        path: '/api/v1/payouts/schedule/{groupId}',
        summary: 'Get payout schedule for a group',
        security: [['bearerAuth' => []]],
        tags: ['Payouts'],
        parameters: [
            new OA\Parameter(
                name: 'groupId',
                description: 'Group ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payout schedule retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Payout schedule retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'payout_day', type: 'integer', example: 1),
                                    new OA\Property(property: 'payout_date', type: 'string', format: 'date', example: '2024-01-15'),
                                    new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'user_name', type: 'string', example: 'John Doe'),
                                    new OA\Property(property: 'position_number', type: 'integer', example: 1),
                                    new OA\Property(property: 'amount', type: 'number', example: 10000.00),
                                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                    new OA\Property(property: 'has_received_payout', type: 'boolean', example: false),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - User is not a member of this group'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function schedule($groupId)
    {
        try {
            $user = auth()->user();

            // Validate group exists
            $group = Group::find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Verify user is a member of the group
            $isMember = $group->members()->where('user_id', $user->id)->exists();
            if (!$isMember) {
                return $this->errorResponse('You are not a member of this group', 403);
            }

            // Get all group members with their payout schedule
            $schedule = $group->members()
                ->with('user:id,name')
                ->orderBy('position_number')
                ->get()
                ->map(function ($member) use ($group) {
                    // Get payout if it exists
                    $payout = Payout::where('group_id', $group->id)
                        ->where('user_id', $member->user_id)
                        ->where('payout_day', $member->payout_day)
                        ->first();

                    return [
                        'payout_day' => $member->payout_day,
                        'payout_date' => $group->start_date 
                            ? $group->start_date->addDays($member->payout_day - 1)->format('Y-m-d')
                            : null,
                        'user_id' => $member->user_id,
                        'user_name' => $member->user->name,
                        'position_number' => $member->position_number,
                        'amount' => $group->contribution_amount * $group->total_members,
                        'status' => $payout ? $payout->status : 'pending',
                        'has_received_payout' => $member->has_received_payout,
                    ];
                });

            Log::info('Payout schedule retrieved', [
                'group_id' => $groupId,
                'user_id' => $user->id,
            ]);

            return $this->successResponse(
                $schedule,
                'Payout schedule retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payout schedule', [
                'group_id' => $groupId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to retrieve payout schedule: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/payouts/history',
        summary: 'Get user payout history',
        security: [['bearerAuth' => []]],
        tags: ['Payouts'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Items per page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 15)
            ),
            new OA\Parameter(
                name: 'status',
                description: 'Filter by status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'processing', 'successful', 'failed'])
            ),
            new OA\Parameter(
                name: 'group_id',
                description: 'Filter by group ID',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payout history retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Payout history retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'group_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'group_name', type: 'string', example: 'Monthly Savings'),
                                            new OA\Property(property: 'amount', type: 'number', example: 10000.00),
                                            new OA\Property(property: 'payout_day', type: 'integer', example: 1),
                                            new OA\Property(property: 'payout_date', type: 'string', format: 'date', example: '2024-01-15'),
                                            new OA\Property(property: 'status', type: 'string', example: 'successful'),
                                            new OA\Property(property: 'payout_method', type: 'string', example: 'wallet'),
                                            new OA\Property(property: 'processed_at', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 50),
                                new OA\Property(property: 'last_page', type: 'integer', example: 4),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function history()
    {
        try {
            $user = auth()->user();

            // Get query parameters
            $perPage = request()->input('per_page', 15);
            $status = request()->input('status');
            $groupId = request()->input('group_id');

            // Build query
            $query = Payout::where('user_id', $user->id)
                ->with('group:id,name')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($groupId) {
                $query->where('group_id', $groupId);
            }

            // Paginate results
            $payouts = $query->paginate($perPage);

            // Transform data
            $data = [
                'data' => collect($payouts->items())->map(function ($payout) {
                    return [
                        'id' => $payout->id,
                        'group_id' => $payout->group_id,
                        'group_name' => $payout->group->name,
                        'amount' => $payout->amount,
                        'payout_day' => $payout->payout_day,
                        'payout_date' => $payout->payout_date->format('Y-m-d'),
                        'status' => $payout->status,
                        'payout_method' => $payout->payout_method,
                        'processed_at' => $payout->processed_at?->toIso8601String(),
                        'created_at' => $payout->created_at->toIso8601String(),
                    ];
                })->toArray(),
                'current_page' => $payouts->currentPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
                'last_page' => $payouts->lastPage(),
            ];

            Log::info('Payout history retrieved', [
                'user_id' => $user->id,
                'filters' => compact('status', 'groupId'),
            ]);

            return $this->successResponse(
                $data,
                'Payout history retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payout history', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to retrieve payout history: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/payouts/{id}',
        summary: 'Get payout details',
        security: [['bearerAuth' => []]],
        tags: ['Payouts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Payout ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payout details retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Payout details retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'group_id', type: 'integer', example: 1),
                                new OA\Property(property: 'group_name', type: 'string', example: 'Monthly Savings'),
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'amount', type: 'number', example: 10000.00),
                                new OA\Property(property: 'payout_day', type: 'integer', example: 1),
                                new OA\Property(property: 'payout_date', type: 'string', format: 'date', example: '2024-01-15'),
                                new OA\Property(property: 'status', type: 'string', example: 'successful'),
                                new OA\Property(property: 'payout_method', type: 'string', example: 'wallet'),
                                new OA\Property(property: 'payout_reference', type: 'string', nullable: true),
                                new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
                                new OA\Property(property: 'processed_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - User does not own this payout'),
            new OA\Response(response: 404, description: 'Payout not found'),
        ]
    )]
    public function show($id)
    {
        try {
            $user = auth()->user();

            // Find payout with group relationship
            $payout = Payout::with('group:id,name')->find($id);
            
            if (!$payout) {
                return $this->errorResponse('Payout not found', 404);
            }

            // Verify user is authorized (owns the payout)
            if ($payout->user_id !== $user->id) {
                return $this->errorResponse('Unauthorized to view this payout', 403);
            }

            // Format response
            $data = [
                'id' => $payout->id,
                'group_id' => $payout->group_id,
                'group_name' => $payout->group->name,
                'user_id' => $payout->user_id,
                'amount' => $payout->amount,
                'payout_day' => $payout->payout_day,
                'payout_date' => $payout->payout_date->format('Y-m-d'),
                'status' => $payout->status,
                'payout_method' => $payout->payout_method,
                'payout_reference' => $payout->payout_reference,
                'failure_reason' => $payout->failure_reason,
                'processed_at' => $payout->processed_at?->toIso8601String(),
                'created_at' => $payout->created_at->toIso8601String(),
                'updated_at' => $payout->updated_at->toIso8601String(),
            ];

            Log::info('Payout details retrieved', [
                'payout_id' => $id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse(
                $data,
                'Payout details retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payout details', [
                'payout_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to retrieve payout details: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/payouts/{id}/retry',
        summary: 'Retry a failed payout',
        security: [['bearerAuth' => []]],
        tags: ['Payouts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Payout ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payout retry initiated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Payout retry initiated successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'group_id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'amount', type: 'number', example: 10000.00),
                                new OA\Property(property: 'payout_day', type: 'integer', example: 1),
                                new OA\Property(property: 'payout_date', type: 'string', format: 'date', example: '2024-01-15'),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'payout_method', type: 'string', example: 'wallet'),
                                new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
                                new OA\Property(property: 'processed_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - User does not own this payout'),
            new OA\Response(response: 404, description: 'Payout not found'),
            new OA\Response(response: 422, description: 'Payout is not in failed status'),
        ]
    )]
    public function retry($id)
    {
        try {
            $user = auth()->user();

            // Validate payout exists
            $payout = Payout::find($id);
            if (!$payout) {
                return $this->errorResponse('Payout not found', 404);
            }

            // Verify user is authorized (owns the payout)
            if ($payout->user_id !== $user->id) {
                return $this->errorResponse('Unauthorized to retry this payout', 403);
            }

            // Call PayoutService.retryFailedPayout()
            $updatedPayout = $this->payoutService->retryFailedPayout($payout);

            Log::info('Payout retry endpoint called', [
                'payout_id' => $id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse(
                $updatedPayout,
                'Payout retry initiated successfully',
                200
            );

        } catch (\Exception $e) {
            // Return 422 if payout is not in failed status
            if (str_contains($e->getMessage(), 'cannot be retried')) {
                return $this->errorResponse($e->getMessage(), 422);
            }

            Log::error('Payout retry failed', [
                'payout_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to retry payout: ' . $e->getMessage(), 500);
        }
    }
}
