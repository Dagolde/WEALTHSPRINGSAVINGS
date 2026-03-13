<?php

namespace App\Http\Controllers\Api;

use App\Services\AdminService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminController extends ApiController
{
    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Get dashboard statistics.
     */
    #[OA\Get(
        path: '/api/v1/admin/dashboard/stats',
        summary: 'Get admin dashboard statistics',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard statistics retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Dashboard statistics retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'users',
                                    properties: [
                                        new OA\Property(property: 'total', type: 'integer', example: 1000),
                                        new OA\Property(property: 'active', type: 'integer', example: 850),
                                        new OA\Property(property: 'suspended', type: 'integer', example: 50),
                                        new OA\Property(property: 'inactive', type: 'integer', example: 100),
                                        new OA\Property(property: 'kyc_pending', type: 'integer', example: 200),
                                        new OA\Property(property: 'kyc_verified', type: 'integer', example: 700),
                                        new OA\Property(property: 'kyc_rejected', type: 'integer', example: 100),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'groups',
                                    properties: [
                                        new OA\Property(property: 'total', type: 'integer', example: 150),
                                        new OA\Property(property: 'pending', type: 'integer', example: 30),
                                        new OA\Property(property: 'active', type: 'integer', example: 100),
                                        new OA\Property(property: 'completed', type: 'integer', example: 15),
                                        new OA\Property(property: 'cancelled', type: 'integer', example: 5),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'transactions',
                                    properties: [
                                        new OA\Property(property: 'total_volume', type: 'string', example: '5000000.00'),
                                        new OA\Property(property: 'total_contributions', type: 'integer', example: 5000),
                                        new OA\Property(property: 'total_payouts', type: 'integer', example: 500),
                                        new OA\Property(property: 'total_withdrawals', type: 'integer', example: 300),
                                        new OA\Property(property: 'success_rate', type: 'string', example: '98.5'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'system_health',
                                    properties: [
                                        new OA\Property(property: 'database_status', type: 'string', example: 'healthy'),
                                        new OA\Property(property: 'pending_withdrawals', type: 'integer', example: 10),
                                        new OA\Property(property: 'failed_payouts', type: 'integer', example: 2),
                                        new OA\Property(property: 'pending_kyc', type: 'integer', example: 200),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'FORBIDDEN'),
                                new OA\Property(property: 'message', type: 'string', example: 'Access denied. Admin privileges required.'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function getDashboardStats()
    {
        $stats = $this->adminService->getDashboardStats();

        return $this->successResponse($stats, 'Dashboard statistics retrieved successfully');
    }


    /**
     * List all users with filters.
     */
    #[OA\Get(
        path: '/api/v1/admin/users',
        summary: 'List all users',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'suspended', 'inactive'])),
            new OA\Parameter(name: 'kyc_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'verified', 'rejected'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function listUsers(Request $request)
    {
        $filters = $request->only(['status', 'kyc_status', 'search']);
        $perPage = $request->input('per_page', 15);

        $users = $this->adminService->listUsers($filters, $perPage);

        return $this->successResponse($users, 'Users retrieved successfully');
    }

    /**
     * Get user details.
     */
    #[OA\Get(
        path: '/api/v1/admin/users/{id}',
        summary: 'Get user details',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User details retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function getUserDetails($id)
    {
        $user = $this->adminService->getUserDetails($id);

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->successResponse($user, 'User details retrieved successfully');
    }

    /**
     * Suspend a user.
     */
    #[OA\Put(
        path: '/api/v1/admin/users/{id}/suspend',
        summary: 'Suspend a user',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Suspicious activity detected'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User suspended successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function suspendUser(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $user = $this->adminService->suspendUser($id, $request->user()->id, $request->reason);
            return $this->successResponse($user, 'User suspended successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Activate a user.
     */
    #[OA\Put(
        path: '/api/v1/admin/users/{id}/activate',
        summary: 'Activate a user',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User activated successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function activateUser($id, Request $request)
    {
        try {
            $user = $this->adminService->activateUser($id, $request->user()->id);
            return $this->successResponse($user, 'User activated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * List pending KYC submissions.
     */
    #[OA\Get(
        path: '/api/v1/admin/kyc/pending',
        summary: 'List pending KYC submissions',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pending KYC submissions retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function listPendingKyc(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $submissions = $this->adminService->getPendingKycSubmissions($perPage);

        return $this->successResponse($submissions, 'Pending KYC submissions retrieved successfully');
    }

    /**
     * Approve KYC submission.
     */
    #[OA\Post(
        path: '/api/v1/admin/kyc/{id}/approve',
        summary: 'Approve KYC submission',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'KYC approved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function approveKyc($id, Request $request)
    {
        try {
            $user = $this->adminService->approveKyc($id, $request->user()->id);
            return $this->successResponse($user, 'KYC approved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Reject KYC submission.
     */
    #[OA\Post(
        path: '/api/v1/admin/kyc/{id}/reject',
        summary: 'Reject KYC submission',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Document not clear'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'KYC rejected successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function rejectKyc(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $user = $this->adminService->rejectKyc($id, $request->user()->id, $request->reason);
            return $this->successResponse($user, 'KYC rejected successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * List all groups.
     */
    #[OA\Get(
        path: '/api/v1/admin/groups',
        summary: 'List all groups',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'active', 'completed', 'cancelled'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Groups retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function listGroups(Request $request)
    {
        $filters = $request->only(['status', 'search']);
        $perPage = $request->input('per_page', 15);

        $groups = $this->adminService->listGroups($filters, $perPage);

        return $this->successResponse($groups, 'Groups retrieved successfully');
    }

    /**
     * Get group details.
     */
    #[OA\Get(
        path: '/api/v1/admin/groups/{id}',
        summary: 'Get group details',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Group details retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function getGroupDetails($id)
    {
        $group = $this->adminService->getGroupDetails($id);

        if (!$group) {
            return $this->errorResponse('Group not found', 404);
        }

        return $this->successResponse($group, 'Group details retrieved successfully');
    }

    /**
     * List pending withdrawals.
     */
    #[OA\Get(
        path: '/api/v1/admin/withdrawals/pending',
        summary: 'List pending withdrawals',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pending withdrawals retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function listPendingWithdrawals(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $withdrawals = $this->adminService->listPendingWithdrawals($perPage);

        return $this->successResponse($withdrawals, 'Pending withdrawals retrieved successfully');
    }

    /**
     * Approve withdrawal.
     */
    #[OA\Post(
        path: '/api/v1/admin/withdrawals/{id}/approve',
        summary: 'Approve withdrawal',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Withdrawal approved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Withdrawal not found'),
        ]
    )]
    public function approveWithdrawal($id, Request $request)
    {
        try {
            $withdrawal = $this->adminService->approveWithdrawal($id, $request->user()->id);
            return $this->successResponse($withdrawal, 'Withdrawal approved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Reject withdrawal.
     */
    #[OA\Post(
        path: '/api/v1/admin/withdrawals/{id}/reject',
        summary: 'Reject withdrawal',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Insufficient documentation'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Withdrawal rejected successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Withdrawal not found'),
        ]
    )]
    public function rejectWithdrawal(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $withdrawal = $this->adminService->rejectWithdrawal($id, $request->user()->id, $request->reason);
            return $this->successResponse($withdrawal, 'Withdrawal rejected successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Get user analytics.
     */
    #[OA\Get(
        path: '/api/v1/admin/analytics/users',
        summary: 'Get user analytics (growth, retention)',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-31')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'json'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User analytics retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function getUserAnalytics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'export' => 'nullable|in:csv,json',
        ]);

        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : null;
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : null;

        $analytics = $this->adminService->getUserAnalytics($startDate, $endDate);

        if ($request->export === 'csv') {
            return $this->exportToCsv($analytics, 'user_analytics');
        }

        if ($request->export === 'json') {
            return response()->json($analytics);
        }

        return $this->successResponse($analytics, 'User analytics retrieved successfully');
    }

    /**
     * Get group analytics.
     */
    #[OA\Get(
        path: '/api/v1/admin/analytics/groups',
        summary: 'Get group analytics (performance)',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-31')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'json'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Group analytics retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function getGroupAnalytics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'export' => 'nullable|in:csv,json',
        ]);

        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : null;
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : null;

        $analytics = $this->adminService->getGroupAnalytics($startDate, $endDate);

        if ($request->export === 'csv') {
            return $this->exportToCsv($analytics, 'group_analytics');
        }

        if ($request->export === 'json') {
            return response()->json($analytics);
        }

        return $this->successResponse($analytics, 'Group analytics retrieved successfully');
    }

    /**
     * Get transaction analytics.
     */
    #[OA\Get(
        path: '/api/v1/admin/analytics/transactions',
        summary: 'Get transaction analytics (trends)',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-31')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'json'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Transaction analytics retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function getTransactionAnalytics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'export' => 'nullable|in:csv,json',
        ]);

        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : null;
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : null;

        $analytics = $this->adminService->getTransactionAnalytics($startDate, $endDate);

        if ($request->export === 'csv') {
            return $this->exportToCsv($analytics, 'transaction_analytics');
        }

        if ($request->export === 'json') {
            return response()->json($analytics);
        }

        return $this->successResponse($analytics, 'Transaction analytics retrieved successfully');
    }

    /**
     * Get revenue analytics.
     */
    #[OA\Get(
        path: '/api/v1/admin/analytics/revenue',
        summary: 'Get revenue analytics (platform revenue)',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-31')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'json'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Revenue analytics retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function getRevenueAnalytics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'export' => 'nullable|in:csv,json',
        ]);

        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : null;
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : null;

        $analytics = $this->adminService->getRevenueAnalytics($startDate, $endDate);

        if ($request->export === 'csv') {
            return $this->exportToCsv($analytics, 'revenue_analytics');
        }

        if ($request->export === 'json') {
            return response()->json($analytics);
        }

        return $this->successResponse($analytics, 'Revenue analytics retrieved successfully');
    }

    /**
     * Get system settings.
     */
    #[OA\Get(
        path: '/api/v1/admin/settings',
        summary: 'Get system settings',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'System settings retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function getSettings()
    {
        $settings = $this->adminService->getSystemSettings();
        return $this->successResponse($settings, 'System settings retrieved successfully');
    }

    /**
     * Update system settings.
     */
    #[OA\Put(
        path: '/api/v1/admin/settings',
        summary: 'Update system settings',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'app_name', type: 'string', example: 'Ajo Platform'),
                    new OA\Property(property: 'app_locale', type: 'string', example: 'en'),
                    new OA\Property(property: 'app_timezone', type: 'string', example: 'Africa/Lagos'),
                    new OA\Property(property: 'paystack_public_key', type: 'string', example: 'pk_test_xxxx'),
                    new OA\Property(property: 'paystack_secret_key', type: 'string', example: 'sk_test_xxxx'),
                    new OA\Property(property: 'mail_from_address', type: 'string', example: 'noreply@ajo.test'),
                    new OA\Property(property: 'mail_from_name', type: 'string', example: 'Ajo Platform'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'System settings updated successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateSettings(Request $request)
    {
        $request->validate([
            'app_name' => 'nullable|string|max:255',
            'app_locale' => 'nullable|string|max:10',
            'app_timezone' => 'nullable|string|max:50',
            'paystack_public_key' => 'nullable|string|max:255',
            'paystack_secret_key' => 'nullable|string|max:255',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
        ]);

        try {
            $settings = $this->adminService->updateSystemSettings($request->all(), $request->user()->id);
            return $this->successResponse($settings, 'System settings updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * List contributions.
     */
    #[OA\Get(
        path: '/api/v1/admin/contributions',
        summary: 'List all contributions',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['successful', 'pending', 'failed'])),
            new OA\Parameter(name: 'group_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contributions retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    public function listContributions(Request $request)
    {
        $filters = $request->only(['status', 'group_id', 'search']);
        $perPage = $request->input('per_page', 15);

        $contributions = $this->adminService->listContributions($filters, $perPage);

        return $this->successResponse($contributions, 'Contributions retrieved successfully');
    }

    /**
     * Get contribution details.
     */
    #[OA\Get(
        path: '/api/v1/admin/contributions/{id}',
        summary: 'Get contribution details',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contribution details retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Contribution not found'),
        ]
    )]
    public function getContributionDetails($id)
    {
        $contribution = $this->adminService->getContributionDetails($id);

        if (!$contribution) {
            return $this->errorResponse('Contribution not found', 404);
        }

        return $this->successResponse($contribution, 'Contribution details retrieved successfully');
    }

    /**
     * Record a contribution.
     */
    #[OA\Post(
        path: '/api/v1/admin/contributions',
        summary: 'Record a contribution',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'group_id', 'amount', 'payment_method'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 1),
                    new OA\Property(property: 'group_id', type: 'integer', example: 1),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 5000.00),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['wallet', 'bank', 'cash', 'card'], example: 'cash'),
                    new OA\Property(property: 'payment_reference', type: 'string', example: 'CASH-2024-001'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Cash payment received'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Contribution recorded successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function recordContribution(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'group_id' => 'required|integer|exists:groups,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:wallet,bank,cash,card',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $contribution = $this->adminService->recordContribution($request->all(), $request->user()->id);
            return $this->successResponse($contribution, 'Contribution recorded successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Verify a contribution.
     */
    #[OA\Post(
        path: '/api/v1/admin/contributions/{id}/verify',
        summary: 'Verify a contribution',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contribution verified successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Contribution not found'),
        ]
    )]
    public function verifyContribution($id, Request $request)
    {
        try {
            $contribution = $this->adminService->verifyContribution($id, $request->user()->id);
            return $this->successResponse($contribution, 'Contribution verified successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Get group members.
     */
    #[OA\Get(
        path: '/api/v1/admin/groups/{id}/members',
        summary: 'Get group members',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Group members retrieved successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function getGroupMembers($id)
    {
        try {
            $members = $this->adminService->getGroupMembers($id);
            return $this->successResponse($members, 'Group members retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * Update admin user permissions.
     */
    #[OA\Put(
        path: '/api/v1/admin/users/{id}/permissions',
        summary: 'Update admin user permissions',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['permissions'],
                properties: [
                    new OA\Property(
                        property: 'permissions',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'manage_users', type: 'boolean', example: true),
                            new OA\Property(property: 'approve_kyc', type: 'boolean', example: true),
                            new OA\Property(property: 'manage_groups', type: 'boolean', example: false),
                            new OA\Property(property: 'approve_withdrawals', type: 'boolean', example: true),
                            new OA\Property(property: 'view_analytics', type: 'boolean', example: false),
                            new OA\Property(property: 'manage_settings', type: 'boolean', example: false),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permissions updated successfully'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function updatePermissions(Request $request, $id)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.manage_users' => 'required|boolean',
            'permissions.approve_kyc' => 'required|boolean',
            'permissions.manage_groups' => 'required|boolean',
            'permissions.approve_withdrawals' => 'required|boolean',
            'permissions.view_analytics' => 'required|boolean',
            'permissions.manage_settings' => 'required|boolean',
        ]);

        try {
            $user = $this->adminService->updatePermissions($id, $request->permissions, $request->user()->id);
            return $this->successResponse($user, 'Permissions updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Get mobile app settings.
     */
    public function getMobileSettings()
    {
        $settings = $this->adminService->getMobileAppSettings();
        return $this->successResponse($settings, 'Mobile app settings retrieved successfully');
    }

    /**
     * Update mobile app settings.
     */
    public function updateMobileSettings(Request $request)
    {
        $request->validate([
            'app_version' => 'nullable|string|max:20',
            'min_supported_version' => 'nullable|string|max:20',
            'force_update' => 'nullable|boolean',
            'maintenance_mode' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:500',
            'features' => 'nullable|array',
        ]);

        try {
            $settings = $this->adminService->updateMobileAppSettings($request->all(), $request->user()->id);
            return $this->successResponse($settings, 'Mobile app settings updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Get active user sessions.
     */
    public function getActiveSessions(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $sessions = $this->adminService->getActiveSessions($perPage);
        return $this->successResponse($sessions, 'Active sessions retrieved successfully');
    }

    /**
     * Revoke user session.
     */
    public function revokeSession($id, Request $request)
    {
        try {
            $this->adminService->revokeSession($id, $request->user()->id);
            return $this->successResponse(null, 'Session revoked successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * Revoke all user sessions.
     */
    public function revokeAllUserSessions($id, Request $request)
    {
        try {
            $count = $this->adminService->revokeAllUserSessions($id, $request->user()->id);
            return $this->successResponse(['revoked_count' => $count], "Revoked $count sessions successfully");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Send push notification.
     */
    public function sendPushNotification(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:500',
            'type' => 'nullable|string|max:50',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'data' => 'nullable|array',
        ]);

        try {
            $result = $this->adminService->sendPushNotification($request->all(), $request->user()->id);
            return $this->successResponse($result, 'Push notification sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Get app usage statistics.
     */
    public function getAppUsage()
    {
        $stats = $this->adminService->getAppUsageStats();
        return $this->successResponse($stats, 'App usage statistics retrieved successfully');
    }

    /**
     * Export analytics data to CSV.
     */
    private function exportToCsv(array $data, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}_" . date('Y-m-d') . ".csv\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // Write period information
            if (isset($data['period'])) {
                fputcsv($file, ['Period', 'Start Date', 'End Date']);
                fputcsv($file, ['', $data['period']['start_date'], $data['period']['end_date']]);
                fputcsv($file, []); // Empty row
            }

            // Write summary metrics
            fputcsv($file, ['Metric', 'Value']);
            foreach ($data as $key => $value) {
                if ($key !== 'period' && !is_array($value)) {
                    fputcsv($file, [ucwords(str_replace('_', ' ', $key)), $value]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

    /**
     * Get all wallet transactions with filters
     */
    public function getTransactions(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $status = $request->input('status'); // pending, successful, failed
        $type = $request->input('type'); // credit, debit
        $search = $request->input('search'); // search by user name, email, reference
        
        $query = \App\Models\WalletTransaction::with('user:id,name,email')
            ->orderBy('created_at', 'desc');
        
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($type) {
            $query->where('type', $type);
        }
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->WhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }
        
        $transactions = $query->paginate($perPage);
        
        return $this->successResponse($transactions, 'Transactions retrieved successfully');
    }

    /**
     * Get single transaction details
     */
    public function getTransaction($id)
    {
        $transaction = \App\Models\WalletTransaction::with('user:id,name,email,phone')
            ->findOrFail($id);
        
        return $this->successResponse($transaction, 'Transaction retrieved successfully');
    }

    /**
     * Approve a pending transaction
     */
    public function approveTransaction($id)
    {
        $transaction = \App\Models\WalletTransaction::findOrFail($id);
        
        if ($transaction->status !== 'pending') {
            return $this->errorResponse('Only pending transactions can be approved', 400);
        }
        
        \DB::transaction(function() use ($transaction) {
            // Update transaction status
            $transaction->status = 'successful';
            $transaction->save();
            
            // Update user wallet balance if it's a credit transaction
            if ($transaction->type === 'credit') {
                $user = $transaction->user;
                $user->wallet_balance += $transaction->amount;
                $user->save();
            }
        });
        
        return $this->successResponse($transaction, 'Transaction approved successfully');
    }

    /**
     * Reject a pending transaction
     */
    public function rejectTransaction(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);
        
        $transaction = \App\Models\WalletTransaction::findOrFail($id);
        
        if ($transaction->status !== 'pending') {
            return $this->errorResponse('Only pending transactions can be rejected', 400);
        }
        
        $transaction->status = 'failed';
        $transaction->metadata = array_merge($transaction->metadata ?? [], [
            'rejection_reason' => $request->reason,
            'rejected_by' => auth()->id(),
            'rejected_at' => now()->toDateTimeString()
        ]);
        $transaction->save();
        
        return $this->successResponse($transaction, 'Transaction rejected successfully');
    }

    /**
     * Delete a transaction (soft delete)
     */
    public function deleteTransaction($id)
    {
        $transaction = \App\Models\WalletTransaction::findOrFail($id);
        
        // Only allow deletion of failed or rejected transactions
        if (!in_array($transaction->status, ['failed', 'rejected'])) {
            return $this->errorResponse('Only failed or rejected transactions can be deleted', 400);
        }
        
        $transaction->delete();
        
        return $this->successResponse(null, 'Transaction deleted successfully');
    }

    /**
     * Get transaction statistics
     */
    public function getTransactionStats()
    {
        $stats = [
            'total' => \App\Models\WalletTransaction::count(),
            'pending' => \App\Models\WalletTransaction::where('status', 'pending')->count(),
            'successful' => \App\Models\WalletTransaction::where('status', 'successful')->count(),
            'failed' => \App\Models\WalletTransaction::where('status', 'failed')->count(),
            'total_volume' => \App\Models\WalletTransaction::where('status', 'successful')->sum('amount'),
            'credit_volume' => \App\Models\WalletTransaction::where('status', 'successful')->where('type', 'credit')->sum('amount'),
            'debit_volume' => \App\Models\WalletTransaction::where('status', 'successful')->where('type', 'debit')->sum('amount'),
        ];
        
        return $this->successResponse($stats, 'Transaction statistics retrieved successfully');
    }
}
