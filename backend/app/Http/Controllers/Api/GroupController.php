<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CreateGroupRequest;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class GroupController extends ApiController
{
    #[OA\Post(
        path: '/api/v1/groups',
        summary: 'Create a new contribution group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'contribution_amount', 'total_members', 'cycle_days', 'frequency'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Monthly Savings Group'),
                    new OA\Property(property: 'description', type: 'string', example: 'A group for monthly savings'),
                    new OA\Property(property: 'contribution_amount', type: 'number', format: 'float', example: 1000.00),
                    new OA\Property(property: 'total_members', type: 'integer', example: 10),
                    new OA\Property(property: 'cycle_days', type: 'integer', example: 10),
                    new OA\Property(property: 'frequency', type: 'string', enum: ['daily', 'weekly'], example: 'daily'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Group created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Group created successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Monthly Savings Group'),
                                new OA\Property(property: 'description', type: 'string', example: 'A group for monthly savings'),
                                new OA\Property(property: 'group_code', type: 'string', example: 'ABC12345'),
                                new OA\Property(property: 'contribution_amount', type: 'number', example: 1000.00),
                                new OA\Property(property: 'total_members', type: 'integer', example: 10),
                                new OA\Property(property: 'current_members', type: 'integer', example: 1),
                                new OA\Property(property: 'cycle_days', type: 'integer', example: 10),
                                new OA\Property(property: 'frequency', type: 'string', example: 'daily'),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'created_by', type: 'integer', example: 1),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(CreateGroupRequest $request)
    {
        try {
            $group = DB::transaction(function () use ($request) {
                // Generate unique group code
                $groupCode = $this->generateUniqueGroupCode();

                // Create the group
                $group = Group::create([
                    'name' => $request->name,
                    'description' => $request->description,
                    'group_code' => $groupCode,
                    'contribution_amount' => $request->contribution_amount,
                    'total_members' => $request->total_members,
                    'current_members' => 1, // Creator is the first member
                    'cycle_days' => $request->cycle_days,
                    'frequency' => $request->frequency,
                    'status' => 'pending',
                    'created_by' => $request->user()->id,
                ]);

                // Automatically add creator as first member
                GroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $request->user()->id,
                    'position_number' => -1, // Temporary value, will be assigned when group starts
                    'payout_day' => 0, // Will be calculated when group starts
                    'has_received_payout' => false,
                    'joined_at' => now(),
                    'status' => 'active',
                ]);

                return $group;
            });

            return $this->successResponse(
                $group->fresh(),
                'Group created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create group: ' . $e->getMessage(),
                500
            );
        }
    }
    #[OA\Post(
        path: '/api/v1/groups/{id}/join',
        summary: 'Join a contribution group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Group ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully joined group',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Successfully joined group'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Monthly Savings Group'),
                                new OA\Property(property: 'group_code', type: 'string', example: 'ABC12345'),
                                new OA\Property(property: 'current_members', type: 'integer', example: 2),
                                new OA\Property(property: 'total_members', type: 'integer', example: 10),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Group not found'),
            new OA\Response(response: 422, description: 'Cannot join group (full, not pending, or already a member)'),
        ]
    )]
    public function join($id)
    {
        try {
            $user = auth()->user();

            $group = DB::transaction(function () use ($id, $user) {
                // Lock the group row for update to prevent race conditions
                $group = Group::where('id', $id)->lockForUpdate()->first();

                if (!$group) {
                    throw new \Exception('Group not found', 404);
                }

                // Validate group is in 'pending' status
                if (!$group->isPending()) {
                    throw new \Exception('Cannot join group. Group is not in pending status.', 422);
                }

                // Check if group is full
                if ($group->isFull()) {
                    throw new \Exception('Cannot join group. Group is already full.', 422);
                }

                // Check if user is already a member
                $existingMember = GroupMember::where('group_id', $group->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if ($existingMember) {
                    throw new \Exception('You are already a member of this group.', 422);
                }

                // Get the next temporary position number (use negative of member count to avoid conflicts)
                $memberCount = GroupMember::where('group_id', $group->id)->count();
                $tempPosition = -($memberCount + 1);

                // Add user as a group member
                GroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'position_number' => $tempPosition, // Temporary negative value, will be assigned when group starts
                    'payout_day' => 0, // Will be calculated when group starts
                    'has_received_payout' => false,
                    'joined_at' => now(),
                    'status' => 'active',
                ]);

                // Atomically increment current_members count
                $group->increment('current_members');

                return $group->fresh();
            });

            return $this->successResponse(
                $group,
                'Successfully joined group'
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
    #[OA\Post(
        path: '/api/v1/groups/join',
        summary: 'Join a contribution group by group code',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['group_code'],
                properties: [
                    new OA\Property(property: 'group_code', type: 'string', example: 'ABC12345'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully joined group',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Successfully joined group'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Monthly Savings Group'),
                                new OA\Property(property: 'group_code', type: 'string', example: 'ABC12345'),
                                new OA\Property(property: 'current_members', type: 'integer', example: 2),
                                new OA\Property(property: 'total_members', type: 'integer', example: 10),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Group not found'),
            new OA\Response(response: 422, description: 'Cannot join group (full, not pending, or already a member)'),
        ]
    )]
    public function joinByCode()
    {
        try {
            $user = auth()->user();
            $groupCode = request()->input('group_code');

            if (!$groupCode) {
                return $this->errorResponse('Group code is required', 422);
            }

            $group = DB::transaction(function () use ($groupCode, $user) {
                // Find group by code and lock for update
                $group = Group::where('group_code', $groupCode)->lockForUpdate()->first();

                if (!$group) {
                    throw new \Exception('Group not found', 404);
                }

                // Validate group is in 'pending' status
                if (!$group->isPending()) {
                    throw new \Exception('Cannot join group. Group is not in pending status.', 422);
                }

                // Check if group is full
                if ($group->isFull()) {
                    throw new \Exception('Cannot join group. Group is already full.', 422);
                }

                // Check if user is already a member
                $existingMember = GroupMember::where('group_id', $group->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if ($existingMember) {
                    throw new \Exception('You are already a member of this group.', 422);
                }

                // Get the next temporary position number (use negative of member count to avoid conflicts)
                $memberCount = GroupMember::where('group_id', $group->id)->count();
                $tempPosition = -($memberCount + 1);

                // Add user as a group member
                GroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'position_number' => $tempPosition, // Temporary negative value, will be assigned when group starts
                    'payout_day' => 0, // Will be calculated when group starts
                    'has_received_payout' => false,
                    'joined_at' => now(),
                    'status' => 'active',
                ]);

                // Atomically increment current_members count
                $group->increment('current_members');

                return $group->fresh();
            });

            return $this->successResponse(
                $group,
                'Successfully joined group'
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


    #[OA\Post(
        path: '/api/v1/groups/{id}/start',
        summary: 'Start a contribution group by assigning positions',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Group ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group started successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Group started successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Monthly Savings Group'),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2024-01-15'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2024-01-24'),
                                new OA\Property(property: 'current_members', type: 'integer', example: 10),
                                new OA\Property(property: 'total_members', type: 'integer', example: 10),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Only group creator can start the group'),
            new OA\Response(response: 404, description: 'Group not found'),
            new OA\Response(response: 422, description: 'Cannot start group (not full or not pending)'),
        ]
    )]
    public function start($id)
    {
        try {
            $user = auth()->user();

            $group = DB::transaction(function () use ($id, $user) {
                // Lock the group row for update to prevent race conditions
                $group = Group::where('id', $id)->lockForUpdate()->first();

                if (!$group) {
                    throw new \Exception('Group not found', 404);
                }

                // Verify the authenticated user is the group creator
                if ($group->created_by !== $user->id) {
                    throw new \Exception('Only the group creator can start the group', 403);
                }

                // Verify the group is in 'pending' status
                if (!$group->isPending()) {
                    throw new \Exception('Cannot start group. Group is not in pending status.', 422);
                }

                // Verify the group is full
                if (!$group->isFull()) {
                    throw new \Exception(
                        "Cannot start group. Group is not full. Current members: {$group->current_members}, Required: {$group->total_members}",
                        422
                    );
                }

                // Get all members of the group
                $members = GroupMember::where('group_id', $group->id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->get();

                // Verify we have the correct number of members
                if ($members->count() !== $group->total_members) {
                    throw new \Exception('Member count mismatch. Cannot start group.', 422);
                }

                // Generate random unique position numbers (1 to N)
                $positions = range(1, $group->total_members);
                shuffle($positions);

                // Set start_date to today
                $startDate = now()->startOfDay();
                
                // Calculate end_date based on cycle_days
                // For daily frequency: end_date = start_date + (cycle_days - 1) days
                // For weekly frequency: end_date = start_date + (cycle_days - 1) weeks
                if ($group->frequency === 'weekly') {
                    $endDate = $startDate->copy()->addWeeks($group->cycle_days - 1);
                } else {
                    $endDate = $startDate->copy()->addDays($group->cycle_days - 1);
                }

                // Assign positions and calculate payout_day for each member
                foreach ($members as $index => $member) {
                    $position = $positions[$index];
                    
                    // Calculate payout_day: position number (1-based)
                    // The payout happens on day N where N is the position
                    $payoutDay = $position;

                    $member->update([
                        'position_number' => $position,
                        'payout_day' => $payoutDay,
                    ]);
                }

                // Update group status to 'active' and set dates
                $group->update([
                    'status' => 'active',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);

                return $group->fresh();
            });

            return $this->successResponse(
                $group,
                'Group started successfully'
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
        path: '/api/v1/groups',
        summary: 'List user\'s groups',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Filter by group status',
                schema: new OA\Schema(type: 'string', enum: ['pending', 'active', 'completed', 'cancelled'])
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Page number',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Items per page',
                schema: new OA\Schema(type: 'integer', default: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of user\'s groups',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Groups retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'Monthly Savings Group'),
                                            new OA\Property(property: 'description', type: 'string', example: 'A group for monthly savings'),
                                            new OA\Property(property: 'group_code', type: 'string', example: 'ABC12345'),
                                            new OA\Property(property: 'contribution_amount', type: 'number', example: 1000.00),
                                            new OA\Property(property: 'total_members', type: 'integer', example: 10),
                                            new OA\Property(property: 'current_members', type: 'integer', example: 10),
                                            new OA\Property(property: 'cycle_days', type: 'integer', example: 10),
                                            new OA\Property(property: 'frequency', type: 'string', example: 'daily'),
                                            new OA\Property(property: 'status', type: 'string', example: 'active'),
                                            new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2024-01-15'),
                                            new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2024-01-24'),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 25),
                                new OA\Property(property: 'last_page', type: 'integer', example: 2),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index()
    {
        try {
            $user = auth()->user();
            $perPage = request()->input('per_page', 15);
            $status = request()->input('status');

            // Get groups where user is a member
            $query = Group::whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('status', 'active');
            });

            // Apply status filter if provided
            if ($status && in_array($status, ['pending', 'active', 'completed', 'cancelled'])) {
                $query->byStatus($status);
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $groups = $query->paginate($perPage);

            return $this->successResponse(
                $groups,
                'Groups retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve groups: ' . $e->getMessage(),
                500
            );
        }
    }

    #[OA\Get(
        path: '/api/v1/groups/{id}',
        summary: 'Get group details',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Group ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Group details retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Monthly Savings Group'),
                                new OA\Property(property: 'description', type: 'string', example: 'A group for monthly savings'),
                                new OA\Property(property: 'group_code', type: 'string', example: 'ABC12345'),
                                new OA\Property(property: 'contribution_amount', type: 'number', example: 1000.00),
                                new OA\Property(property: 'total_members', type: 'integer', example: 10),
                                new OA\Property(property: 'current_members', type: 'integer', example: 10),
                                new OA\Property(property: 'cycle_days', type: 'integer', example: 10),
                                new OA\Property(property: 'frequency', type: 'string', example: 'daily'),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2024-01-15'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2024-01-24'),
                                new OA\Property(
                                    property: 'creator',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'member_count', type: 'integer', example: 10),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Not a member of this group'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function show($id)
    {
        try {
            $user = auth()->user();

            $group = Group::with('creator:id,name,email')->find($id);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Verify user is a member of the group
            $isMember = GroupMember::where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (!$isMember) {
                return $this->errorResponse('You are not a member of this group', 403);
            }

            // Add member count
            $groupData = $group->toArray();
            $groupData['member_count'] = $group->members()->where('status', 'active')->count();

            return $this->successResponse(
                $groupData,
                'Group details retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve group details: ' . $e->getMessage(),
                500
            );
        }
    }

    #[OA\Get(
        path: '/api/v1/groups/{id}/members',
        summary: 'Get group members',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Group ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of group members',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Group members retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'position_number', type: 'integer', example: 1),
                                    new OA\Property(property: 'payout_day', type: 'integer', example: 1),
                                    new OA\Property(property: 'has_received_payout', type: 'boolean', example: false),
                                    new OA\Property(property: 'joined_at', type: 'string', format: 'date-time'),
                                    new OA\Property(
                                        property: 'user',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                            new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                        ],
                                        type: 'object'
                                    ),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Not a member of this group'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function members($id)
    {
        try {
            $user = auth()->user();

            $group = Group::find($id);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Verify user is a member of the group
            $isMember = GroupMember::where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (!$isMember) {
                return $this->errorResponse('You are not a member of this group', 403);
            }

            // Get all active members ordered by position_number
            $members = GroupMember::where('group_id', $group->id)
                ->where('status', 'active')
                ->with('user:id,name,email')
                ->orderBy('position_number')
                ->get();

            return $this->successResponse(
                $members,
                'Group members retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve group members: ' . $e->getMessage(),
                500
            );
        }
    }

    #[OA\Get(
        path: '/api/v1/groups/{id}/schedule',
        summary: 'Get group payout schedule',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Group ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group payout schedule',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Payout schedule retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'position_number', type: 'integer', example: 1),
                                    new OA\Property(property: 'payout_day', type: 'integer', example: 1),
                                    new OA\Property(property: 'payout_date', type: 'string', format: 'date', example: '2024-01-15'),
                                    new OA\Property(property: 'has_received_payout', type: 'boolean', example: false),
                                    new OA\Property(
                                        property: 'member',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                            new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                        ],
                                        type: 'object'
                                    ),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Not a member of this group'),
            new OA\Response(response: 404, description: 'Group not found'),
            new OA\Response(response: 422, description: 'Group has not started yet'),
        ]
    )]
    public function schedule($id)
    {
        try {
            $user = auth()->user();

            $group = Group::find($id);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Verify user is a member of the group
            $isMember = GroupMember::where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (!$isMember) {
                return $this->errorResponse('You are not a member of this group', 403);
            }

            // Check if group has started
            if (!$group->start_date) {
                return $this->errorResponse('Group has not started yet. Payout schedule not available.', 422);
            }

            // Get all active members ordered by position_number
            $members = GroupMember::where('group_id', $group->id)
                ->where('status', 'active')
                ->with('user:id,name,email')
                ->orderBy('position_number')
                ->get();

            // Calculate payout schedule
            $schedule = $members->map(function ($member) use ($group) {
                // Calculate payout date based on start_date and position
                // For daily frequency: payout_date = start_date + (position - 1) days
                // For weekly frequency: payout_date = start_date + (position - 1) weeks
                $startDate = \Carbon\Carbon::parse($group->start_date);
                
                if ($group->frequency === 'weekly') {
                    $payoutDate = $startDate->copy()->addWeeks($member->position_number - 1);
                } else {
                    $payoutDate = $startDate->copy()->addDays($member->position_number - 1);
                }

                return [
                    'position_number' => $member->position_number,
                    'payout_day' => $member->payout_day,
                    'payout_date' => $payoutDate->format('Y-m-d'),
                    'has_received_payout' => $member->has_received_payout,
                    'member' => [
                        'id' => $member->user->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                    ],
                ];
            });

            return $this->successResponse(
                $schedule,
                'Payout schedule retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve payout schedule: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Generate a unique 8-character alphanumeric group code.
     *
     * @return string
     */
    private function generateUniqueGroupCode(): string
    {
        do {
            // Generate 8-character alphanumeric code (uppercase letters and numbers)
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            // Check if code already exists
            $exists = Group::where('group_code', $code)->exists();
        } while ($exists);

        return $code;
    }
}
