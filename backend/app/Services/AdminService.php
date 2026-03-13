<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\Payout;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminService
{
    /**
     * Get dashboard statistics (optimized with caching).
     */
    public function getDashboardStats(): array
    {
        return \Cache::remember('admin_dashboard_stats', 300, function () {
            // Single optimized query for user statistics
            $userStats = User::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN kyc_status = ? THEN 1 ELSE 0 END) as kyc_pending,
                SUM(CASE WHEN kyc_status = ? THEN 1 ELSE 0 END) as kyc_verified,
                SUM(CASE WHEN kyc_status = ? THEN 1 ELSE 0 END) as kyc_rejected
            ', ['active', 'suspended', 'inactive', 'pending', 'verified', 'rejected'])
            ->first();

            // Single optimized query for group statistics
            $groupStats = Group::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled
            ', ['pending', 'active', 'completed', 'cancelled'])
            ->first();

            // Single optimized query for transaction statistics
            $transactionStats = DB::selectOne('
                SELECT 
                    COALESCE(SUM(CASE WHEN c.payment_status = ? THEN c.amount ELSE 0 END), 0) as total_contributions,
                    COALESCE(SUM(CASE WHEN p.status = ? THEN p.amount ELSE 0 END), 0) as total_payouts,
                    COALESCE(SUM(CASE WHEN w.status = ? THEN w.amount ELSE 0 END), 0) as total_withdrawals,
                    COUNT(CASE WHEN c.payment_status = ? THEN 1 END) as contribution_count,
                    COUNT(c.id) as total_transactions
                FROM contributions c
                LEFT JOIN payouts p ON 1=1
                LEFT JOIN withdrawals w ON 1=1
            ', ['successful', 'successful', 'successful', 'successful']);

            $successRate = $transactionStats->total_transactions > 0 
                ? ($transactionStats->contribution_count / $transactionStats->total_transactions) * 100 
                : 0;

            // Single optimized query for system health
            $systemHealth = DB::selectOne('
                SELECT 
                    COUNT(CASE WHEN w.admin_approval_status = ? THEN 1 END) as pending_withdrawals,
                    COUNT(CASE WHEN p.status = ? THEN 1 END) as failed_payouts,
                    COUNT(CASE WHEN u.kyc_status = ? THEN 1 END) as pending_kyc
                FROM users u
                LEFT JOIN withdrawals w ON 1=1
                LEFT JOIN payouts p ON 1=1
            ', ['pending', 'failed', 'pending']);

            return [
                'users' => [
                    'total' => (int) $userStats->total,
                    'active' => (int) $userStats->active,
                    'suspended' => (int) $userStats->suspended,
                    'inactive' => (int) $userStats->inactive,
                    'kyc_pending' => (int) $userStats->kyc_pending,
                    'kyc_verified' => (int) $userStats->kyc_verified,
                    'kyc_rejected' => (int) $userStats->kyc_rejected,
                ],
                'groups' => [
                    'total' => (int) $groupStats->total,
                    'pending' => (int) $groupStats->pending,
                    'active' => (int) $groupStats->active,
                    'completed' => (int) $groupStats->completed,
                    'cancelled' => (int) $groupStats->cancelled,
                ],
                'transactions' => [
                    'total_volume' => $transactionStats->total_contributions + $transactionStats->total_payouts + $transactionStats->total_withdrawals,
                    'total_contributions' => $transactionStats->total_contributions,
                    'total_payouts' => $transactionStats->total_payouts,
                    'total_withdrawals' => $transactionStats->total_withdrawals,
                    'contribution_count' => (int) $transactionStats->contribution_count,
                    'success_rate' => round($successRate, 2),
                ],
                'system_health' => [
                    'database_status' => 'healthy',
                    'pending_withdrawals' => (int) $systemHealth->pending_withdrawals,
                    'failed_payouts' => (int) $systemHealth->failed_payouts,
                    'pending_kyc' => (int) $systemHealth->pending_kyc,
                ],
            ];
        });
    }

    /**
     * Get user statistics.
     */
    public function getUserStatistics(): array
    {
        $stats = $this->getDashboardStats();
        return $stats['users'];
    }

    /**
     * Get group statistics.
     */
    public function getGroupStatistics(): array
    {
        $stats = $this->getDashboardStats();
        return $stats['groups'];
    }

    /**
     * Get transaction statistics.
     */
    public function getTransactionStatistics(): array
    {
        $stats = $this->getDashboardStats();
        return $stats['transactions'];
    }

    /**
     * Get system health metrics.
     */
    public function getSystemHealth(): array
    {
        $stats = $this->getDashboardStats();
        return $stats['system_health'];
    }

    /**
     * Check database connection.
     */
    private function checkDatabaseConnection(): string
    {
        try {
            DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Clear dashboard cache.
     */
    public function clearDashboardCache(): void
    {
        \Cache::forget('admin_dashboard_stats');
    }

    /**
     * Suspend a user.
     */
    public function suspendUser(int $userId, int $adminId, string $reason): User
    {
        $user = User::findOrFail($userId);

        if ($user->role === 'admin') {
            throw new \Exception('Cannot suspend admin users');
        }

        $user->update(['status' => 'suspended']);

        // Clear cache
        $this->clearDashboardCache();

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'user_suspended',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'old_values' => json_encode(['status' => 'active']),
            'new_values' => json_encode(['status' => 'suspended', 'reason' => $reason]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $user;
    }

    /**
     * Activate a user.
     */
    public function activateUser(int $userId, int $adminId): User
    {
        $user = User::findOrFail($userId);
        $oldStatus = $user->status;

        $user->update(['status' => 'active']);

        // Clear cache
        $this->clearDashboardCache();

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'user_activated',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'old_values' => json_encode(['status' => $oldStatus]),
            'new_values' => json_encode(['status' => 'active']),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $user;
    }

    /**
     * Get pending KYC submissions.
     */
    public function getPendingKycSubmissions(int $perPage = 15)
    {
        return User::where('kyc_status', 'pending')
            ->whereNotNull('kyc_document_url')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Approve KYC submission.
     */
    public function approveKyc(int $userId, int $adminId): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'kyc_status' => 'verified',
            'kyc_rejection_reason' => null,
        ]);

        // Clear cache
        $this->clearDashboardCache();

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'kyc_approved',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'old_values' => json_encode(['kyc_status' => 'pending']),
            'new_values' => json_encode(['kyc_status' => 'verified']),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Send notification to user
        $notificationService = app(NotificationService::class);
        $notificationService->sendKYCStatusUpdate($user, 'verified');

        return $user;
    }

    /**
     * Reject KYC submission.
     */
    public function rejectKyc(int $userId, int $adminId, string $reason): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'kyc_status' => 'rejected',
            'kyc_rejection_reason' => $reason,
        ]);

        // Clear cache
        $this->clearDashboardCache();

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'kyc_rejected',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'old_values' => json_encode(['kyc_status' => 'pending']),
            'new_values' => json_encode(['kyc_status' => 'rejected', 'reason' => $reason]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Send notification to user
        $notificationService = app(NotificationService::class);
        $notificationService->sendKYCStatusUpdate($user, 'rejected', $reason);

        return $user;
    }

    /**
     * Approve withdrawal.
     */
    public function approveWithdrawal(int $withdrawalId, int $adminId): Withdrawal
    {
        $withdrawal = Withdrawal::findOrFail($withdrawalId);

        if ($withdrawal->admin_approval_status !== 'pending') {
            throw new \Exception('Withdrawal has already been processed');
        }

        $withdrawal->update([
            'admin_approval_status' => 'approved',
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'withdrawal_approved',
            'entity_type' => 'Withdrawal',
            'entity_id' => $withdrawalId,
            'old_values' => json_encode(['admin_approval_status' => 'pending']),
            'new_values' => json_encode(['admin_approval_status' => 'approved']),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $withdrawal;
    }

    /**
     * Reject withdrawal.
     */
    public function rejectWithdrawal(int $withdrawalId, int $adminId, string $reason): Withdrawal
    {
        $withdrawal = Withdrawal::findOrFail($withdrawalId);

        if ($withdrawal->admin_approval_status !== 'pending') {
            throw new \Exception('Withdrawal has already been processed');
        }

        $withdrawal->update([
            'admin_approval_status' => 'rejected',
            'status' => 'rejected',
            'approved_by' => $adminId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'withdrawal_rejected',
            'entity_type' => 'Withdrawal',
            'entity_id' => $withdrawalId,
            'old_values' => json_encode(['admin_approval_status' => 'pending']),
            'new_values' => json_encode(['admin_approval_status' => 'rejected', 'reason' => $reason]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $withdrawal;
    }

    /**
     * List all users with filters (optimized).
     */
    public function listUsers(array $filters, int $perPage = 15)
    {
        $query = User::select([
            'id', 'name', 'email', 'phone', 'status', 'kyc_status', 
            'wallet_balance', 'role', 'created_at'
        ]);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['kyc_status'])) {
            $query->where('kyc_status', $filters['kyc_status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get user details (optimized with eager loading).
     */
    public function getUserDetails(int $userId)
    {
        return User::with([
            'bankAccounts' => function ($query) {
                $query->select('id', 'user_id', 'bank_name', 'account_number', 'account_name');
            },
            'groups' => function ($query) {
                $query->select('groups.id', 'groups.name', 'groups.status');
            }
        ])->find($userId);
    }

    /**
     * List all groups with filters (optimized).
     */
    public function listGroups(array $filters, int $perPage = 15)
    {
        $query = Group::select([
            'id', 'name', 'max_members', 'current_members', 'contribution_amount',
            'contribution_frequency', 'status', 'created_at', 'created_by'
        ])->with(['creator:id,name,email']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('group_code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get group details (optimized with eager loading).
     */
    public function getGroupDetails(int $groupId)
    {
        return Group::with([
            'creator:id,name,email',
            'members' => function ($query) {
                $query->select('group_members.id', 'group_members.group_id', 'group_members.user_id', 
                    'group_members.position_in_queue', 'group_members.status')
                    ->with('user:id,name,email');
            }
        ])->find($groupId);
    }

    /**
     * List pending withdrawals (optimized).
     */
    public function listPendingWithdrawals(int $perPage = 15)
    {
        return Withdrawal::select([
            'id', 'user_id', 'bank_account_id', 'amount', 'status',
            'admin_approval_status', 'created_at'
        ])
        ->with([
            'user:id,name,email',
            'bankAccount:id,bank_name,account_number,account_name'
        ])
        ->where('admin_approval_status', 'pending')
        ->orderBy('created_at', 'asc')
        ->paginate($perPage);
    }

    /**
     * Get user analytics (growth, retention).
     */
    public function getUserAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        // User growth over time
        $userGrowth = User::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // User retention (users who made contributions in the period)
        $activeUsers = Contribution::whereBetween('contributions.created_at', [$startDate, $endDate])
            ->where('payment_status', 'successful')
            ->distinct('user_id')
            ->count('user_id');

        $totalUsers = User::where('created_at', '<', $endDate)->count();
        $retentionRate = $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0;

        // New users in period
        $newUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();

        // KYC verification rate
        $verifiedUsers = User::whereBetween('created_at', [$startDate, $endDate])
            ->where('kyc_status', 'verified')
            ->count();
        $kycVerificationRate = $newUsers > 0 ? ($verifiedUsers / $newUsers) * 100 : 0;

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'user_growth' => $userGrowth,
            'new_users' => $newUsers,
            'active_users' => $activeUsers,
            'total_users' => $totalUsers,
            'retention_rate' => round($retentionRate, 2),
            'kyc_verification_rate' => round($kycVerificationRate, 2),
        ];
    }

    /**
     * Get group analytics (performance).
     */
    public function getGroupAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        // Groups created over time
        $groupCreation = Group::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Group completion rate
        $startedGroups = Group::whereBetween('start_date', [$startDate, $endDate])
            ->whereIn('status', ['active', 'completed'])
            ->count();

        $completedGroups = Group::whereBetween('end_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->count();

        $completionRate = $startedGroups > 0 ? ($completedGroups / $startedGroups) * 100 : 0;

        // Average group size
        $avgGroupSize = Group::whereBetween('created_at', [$startDate, $endDate])
            ->avg('total_members');

        // Average contribution amount
        $avgContributionAmount = Group::whereBetween('created_at', [$startDate, $endDate])
            ->avg('contribution_amount');

        // Groups by status
        $groupsByStatus = Group::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'group_creation' => $groupCreation,
            'groups_started' => $startedGroups,
            'groups_completed' => $completedGroups,
            'completion_rate' => round($completionRate, 2),
            'average_group_size' => round($avgGroupSize ?? 0, 2),
            'average_contribution_amount' => round($avgContributionAmount ?? 0, 2),
            'groups_by_status' => $groupsByStatus,
        ];
    }

    /**
     * Get transaction analytics (trends).
     */
    public function getTransactionAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        // Contributions over time
        $contributionTrends = Contribution::whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'successful')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Payouts over time
        $payoutTrends = Payout::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'successful')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Withdrawals over time
        $withdrawalTrends = Withdrawal::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'successful')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Transaction success rates
        $totalContributions = Contribution::whereBetween('created_at', [$startDate, $endDate])->count();
        $successfulContributions = Contribution::whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'successful')
            ->count();
        $contributionSuccessRate = $totalContributions > 0 ? ($successfulContributions / $totalContributions) * 100 : 0;

        $totalPayouts = Payout::whereBetween('created_at', [$startDate, $endDate])->count();
        $successfulPayouts = Payout::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'successful')
            ->count();
        $payoutSuccessRate = $totalPayouts > 0 ? ($successfulPayouts / $totalPayouts) * 100 : 0;

        // Total volumes
        $totalContributionVolume = Contribution::whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'successful')
            ->sum('amount');

        $totalPayoutVolume = Payout::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'successful')
            ->sum('amount');

        $totalWithdrawalVolume = Withdrawal::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'successful')
            ->sum('amount');

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'contribution_trends' => $contributionTrends,
            'payout_trends' => $payoutTrends,
            'withdrawal_trends' => $withdrawalTrends,
            'contribution_success_rate' => round($contributionSuccessRate, 2),
            'payout_success_rate' => round($payoutSuccessRate, 2),
            'total_contribution_volume' => $totalContributionVolume,
            'total_payout_volume' => $totalPayoutVolume,
            'total_withdrawal_volume' => $totalWithdrawalVolume,
            'total_transaction_volume' => $totalContributionVolume + $totalPayoutVolume + $totalWithdrawalVolume,
        ];
    }

    /**
     * Get revenue analytics (platform revenue).
     */
    public function getRevenueAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        // Platform fees from wallet transactions (assuming 1% fee on funding)
        $walletFundingVolume = WalletTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'credit')
            ->where('purpose', 'wallet_funding')
            ->sum('amount');

        $estimatedFundingFees = $walletFundingVolume * 0.01; // 1% fee

        // Withdrawal fees (assuming flat fee or percentage)
        $withdrawalVolume = Withdrawal::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'successful')
            ->sum('amount');

        $estimatedWithdrawalFees = $withdrawalVolume * 0.005; // 0.5% fee

        // Revenue over time
        $dailyRevenue = WalletTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'credit')
            ->where('purpose', 'wallet_funding')
            ->selectRaw('DATE(created_at) as date, SUM(amount) * 0.01 as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Total revenue
        $totalRevenue = $estimatedFundingFees + $estimatedWithdrawalFees;

        // Average revenue per user
        $activeUsers = WalletTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->distinct('user_id')
            ->count('user_id');

        $revenuePerUser = $activeUsers > 0 ? $totalRevenue / $activeUsers : 0;

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'total_revenue' => round($totalRevenue, 2),
            'funding_fees' => round($estimatedFundingFees, 2),
            'withdrawal_fees' => round($estimatedWithdrawalFees, 2),
            'wallet_funding_volume' => round($walletFundingVolume, 2),
            'withdrawal_volume' => round($withdrawalVolume, 2),
            'daily_revenue' => $dailyRevenue,
            'active_users' => $activeUsers,
            'revenue_per_user' => round($revenuePerUser, 2),
        ];
    }

    /**
     * Get system settings.
     */
    public function getSystemSettings(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_locale' => config('app.locale'),
            'app_timezone' => config('app.timezone'),
            'paystack_public_key' => config('services.paystack.public_key'),
            'paystack_secret_key' => $this->maskSecretKey(config('services.paystack.secret_key')),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
        ];
    }

    /**
     * Update system settings.
     */
    public function updateSystemSettings(array $settings, int $adminId): array
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        $updates = [];

        // Update APP_NAME
        if (isset($settings['app_name'])) {
            $envContent = $this->updateEnvValue($envContent, 'APP_NAME', $settings['app_name']);
            $updates['app_name'] = $settings['app_name'];
        }

        // Update APP_LOCALE
        if (isset($settings['app_locale'])) {
            $envContent = $this->updateEnvValue($envContent, 'APP_LOCALE', $settings['app_locale']);
            $updates['app_locale'] = $settings['app_locale'];
        }

        // Update APP_TIMEZONE
        if (isset($settings['app_timezone'])) {
            $envContent = $this->updateEnvValue($envContent, 'APP_TIMEZONE', $settings['app_timezone']);
            $updates['app_timezone'] = $settings['app_timezone'];
        }

        // Update PAYSTACK_PUBLIC_KEY
        if (isset($settings['paystack_public_key'])) {
            $envContent = $this->updateEnvValue($envContent, 'PAYSTACK_PUBLIC_KEY', $settings['paystack_public_key']);
            $updates['paystack_public_key'] = $settings['paystack_public_key'];
        }

        // Update PAYSTACK_SECRET_KEY
        if (isset($settings['paystack_secret_key']) && !str_contains($settings['paystack_secret_key'], '***')) {
            $envContent = $this->updateEnvValue($envContent, 'PAYSTACK_SECRET_KEY', $settings['paystack_secret_key']);
            $updates['paystack_secret_key'] = $this->maskSecretKey($settings['paystack_secret_key']);
        }

        // Update MAIL_FROM_ADDRESS
        if (isset($settings['mail_from_address'])) {
            $envContent = $this->updateEnvValue($envContent, 'MAIL_FROM_ADDRESS', $settings['mail_from_address']);
            $updates['mail_from_address'] = $settings['mail_from_address'];
        }

        // Update MAIL_FROM_NAME
        if (isset($settings['mail_from_name'])) {
            $envContent = $this->updateEnvValue($envContent, 'MAIL_FROM_NAME', $settings['mail_from_name']);
            $updates['mail_from_name'] = $settings['mail_from_name'];
        }

        // Write updated content back to .env file
        file_put_contents($envPath, $envContent);

        // Log the settings update
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'system_settings_updated',
            'model_type' => 'SystemSettings',
            'model_id' => null,
            'changes' => json_encode($updates),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->getSystemSettings();
    }

    /**
     * Update a value in the .env file content.
     */
    private function updateEnvValue(string $envContent, string $key, string $value): string
    {
        // Escape special characters in value
        $value = str_replace('"', '\"', $value);
        
        // Check if value needs quotes (contains spaces or special characters)
        $needsQuotes = preg_match('/[\s#]/', $value);
        $formattedValue = $needsQuotes ? "\"{$value}\"" : $value;

        // Pattern to match the key with or without quotes
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $envContent)) {
            // Key exists, update it
            $envContent = preg_replace($pattern, "{$key}={$formattedValue}", $envContent);
        } else {
            // Key doesn't exist, append it
            $envContent .= "\n{$key}={$formattedValue}";
        }

        return $envContent;
    }

    /**
     * Mask secret key for display.
     */
    private function maskSecretKey(?string $key): ?string
    {
        if (!$key || strlen($key) < 8) {
            return $key;
        }

        return substr($key, 0, 7) . str_repeat('*', strlen($key) - 11) . substr($key, -4);
    }

    /**
     * List contributions with filters (optimized).
     */
    public function listContributions(array $filters, int $perPage = 15)
    {
        $query = Contribution::select([
            'id', 'user_id', 'group_id', 'amount', 'payment_method',
            'payment_status', 'payment_reference', 'created_at'
        ])->with([
            'user:id,name,email',
            'group:id,name'
        ]);

        if (isset($filters['status'])) {
            $query->where('payment_status', $filters['status']);
        }

        if (isset($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get contribution details (optimized).
     */
    public function getContributionDetails(int $contributionId)
    {
        return Contribution::with([
            'user:id,name,email',
            'group:id,name'
        ])->find($contributionId);
    }

    /**
     * Record a contribution (admin action).
     */
    public function recordContribution(array $data, int $adminId): Contribution
    {
        $contribution = Contribution::create([
            'user_id' => $data['user_id'],
            'group_id' => $data['group_id'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_status' => $data['payment_status'] ?? 'successful',
            'notes' => $data['notes'] ?? null,
        ]);

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'contribution_recorded',
            'entity_type' => 'Contribution',
            'entity_id' => $contribution->id,
            'old_values' => null,
            'new_values' => json_encode($contribution->toArray()),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $contribution->load(['user', 'group']);
    }

    /**
     * Verify a contribution (admin action).
     */
    public function verifyContribution(int $contributionId, int $adminId): Contribution
    {
        $contribution = Contribution::findOrFail($contributionId);

        if ($contribution->payment_status !== 'pending') {
            throw new \Exception('Only pending contributions can be verified');
        }

        $contribution->update([
            'payment_status' => 'successful',
            'verified_at' => now(),
            'verified_by' => $adminId,
        ]);

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'contribution_verified',
            'entity_type' => 'Contribution',
            'entity_id' => $contributionId,
            'old_values' => json_encode(['payment_status' => 'pending']),
            'new_values' => json_encode(['payment_status' => 'successful']),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $contribution->load(['user', 'group']);
    }

    /**
     * Get group members.
     */
    public function getGroupMembers(int $groupId)
    {
        $group = Group::findOrFail($groupId);
        return $group->members()->with('user')->orderBy('position_in_queue')->get();
    }

    /**
     * Update admin user permissions.
     */
    public function updatePermissions(int $userId, array $permissions, int $adminId): User
    {
        $user = User::findOrFail($userId);

        if ($user->role !== 'admin') {
            throw new \Exception('Can only update permissions for admin users');
        }

        if ($user->id === $adminId) {
            throw new \Exception('Cannot modify your own permissions');
        }

        $oldPermissions = $user->permissions ?? [];

        $user->update([
            'permissions' => $permissions,
        ]);

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'permissions_updated',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'old_values' => json_encode($oldPermissions),
            'new_values' => json_encode($permissions),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $user;
    }

    /**
     * Get mobile app settings.
     */
    public function getMobileAppSettings(): array
    {
        return [
            'app_version' => config('app.mobile_version', '1.0.0'),
            'min_supported_version' => config('app.mobile_min_version', '1.0.0'),
            'force_update' => config('app.mobile_force_update', false),
            'maintenance_mode' => config('app.mobile_maintenance', false),
            'maintenance_message' => config('app.mobile_maintenance_message', 'App is under maintenance'),
            'api_base_url' => config('app.url'),
            'features' => [
                'wallet_enabled' => config('features.wallet_enabled', true),
                'groups_enabled' => config('features.groups_enabled', true),
                'contributions_enabled' => config('features.contributions_enabled', true),
                'withdrawals_enabled' => config('features.withdrawals_enabled', true),
                'kyc_required' => config('features.kyc_required', true),
            ],
        ];
    }

    /**
     * Update mobile app settings.
     */
    public function updateMobileAppSettings(array $settings, int $adminId): array
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        $updates = [];

        // Update MOBILE_APP_VERSION
        if (isset($settings['app_version'])) {
            $envContent = $this->updateEnvValue($envContent, 'MOBILE_APP_VERSION', $settings['app_version']);
            $updates['app_version'] = $settings['app_version'];
        }

        // Update MOBILE_MIN_VERSION
        if (isset($settings['min_supported_version'])) {
            $envContent = $this->updateEnvValue($envContent, 'MOBILE_MIN_VERSION', $settings['min_supported_version']);
            $updates['min_supported_version'] = $settings['min_supported_version'];
        }

        // Update MOBILE_FORCE_UPDATE
        if (isset($settings['force_update'])) {
            $envContent = $this->updateEnvValue($envContent, 'MOBILE_FORCE_UPDATE', $settings['force_update'] ? 'true' : 'false');
            $updates['force_update'] = $settings['force_update'];
        }

        // Update MOBILE_MAINTENANCE
        if (isset($settings['maintenance_mode'])) {
            $envContent = $this->updateEnvValue($envContent, 'MOBILE_MAINTENANCE', $settings['maintenance_mode'] ? 'true' : 'false');
            $updates['maintenance_mode'] = $settings['maintenance_mode'];
        }

        // Update MOBILE_MAINTENANCE_MESSAGE
        if (isset($settings['maintenance_message'])) {
            $envContent = $this->updateEnvValue($envContent, 'MOBILE_MAINTENANCE_MESSAGE', $settings['maintenance_message']);
            $updates['maintenance_message'] = $settings['maintenance_message'];
        }

        // Update feature flags
        if (isset($settings['features'])) {
            foreach ($settings['features'] as $feature => $enabled) {
                $envKey = 'FEATURE_' . strtoupper($feature);
                $envContent = $this->updateEnvValue($envContent, $envKey, $enabled ? 'true' : 'false');
                $updates['features'][$feature] = $enabled;
            }
        }

        // Write updated content back to .env file
        file_put_contents($envPath, $envContent);

        // Log the settings update
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'mobile_app_settings_updated',
            'entity_type' => 'MobileAppSettings',
            'entity_id' => null,
            'old_values' => null,
            'new_values' => json_encode($updates),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->getMobileAppSettings();
    }

    /**
     * Get active user sessions.
     */
    public function getActiveSessions(int $perPage = 50)
    {
        return DB::table('personal_access_tokens')
            ->join('users', 'personal_access_tokens.tokenable_id', '=', 'users.id')
            ->select([
                'personal_access_tokens.id',
                'personal_access_tokens.tokenable_id as user_id',
                'users.name as user_name',
                'users.email as user_email',
                'personal_access_tokens.name as device_name',
                'personal_access_tokens.last_used_at',
                'personal_access_tokens.created_at',
                DB::raw("CASE WHEN personal_access_tokens.last_used_at > NOW() - INTERVAL '15 minutes' THEN true ELSE false END as is_active")
            ])
            ->where('personal_access_tokens.tokenable_type', 'App\\Models\\User')
            ->orderBy('personal_access_tokens.last_used_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Revoke user session.
     */
    public function revokeSession(int $tokenId, int $adminId): bool
    {
        $token = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
        
        if (!$token) {
            throw new \Exception('Session not found');
        }

        DB::table('personal_access_tokens')->where('id', $tokenId)->delete();

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'session_revoked',
            'entity_type' => 'Session',
            'entity_id' => $tokenId,
            'old_values' => json_encode(['user_id' => $token->tokenable_id]),
            'new_values' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return true;
    }

    /**
     * Revoke all user sessions.
     */
    public function revokeAllUserSessions(int $userId, int $adminId): int
    {
        $count = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('tokenable_id', $userId)
            ->delete();

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'all_sessions_revoked',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'old_values' => json_encode(['sessions_count' => $count]),
            'new_values' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $count;
    }

    /**
     * Send push notification to users.
     */
    public function sendPushNotification(array $data, int $adminId): array
    {
        $userIds = $data['user_ids'] ?? [];
        $title = $data['title'];
        $message = $data['message'];
        $type = $data['type'] ?? 'general';

        $sent = 0;
        $failed = 0;

        if (empty($userIds)) {
            // Send to all users
            $users = User::where('status', 'active')->get();
        } else {
            // Send to specific users
            $users = User::whereIn('id', $userIds)->where('status', 'active')->get();
        }

        foreach ($users as $user) {
            try {
                // Create notification record
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => json_encode($data['data'] ?? []),
                    'is_read' => false,
                ]);

                // TODO: Send actual push notification via Firebase/OneSignal
                // This would integrate with your push notification service

                $sent++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        // Log the action
        AuditLog::create([
            'user_id' => $adminId,
            'action' => 'push_notification_sent',
            'entity_type' => 'Notification',
            'entity_id' => null,
            'old_values' => null,
            'new_values' => json_encode([
                'title' => $title,
                'recipients' => count($users),
                'sent' => $sent,
                'failed' => $failed,
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($users),
        ];
    }

    /**
     * Get app usage statistics.
     */
    public function getAppUsageStats(): array
    {
        // Active sessions in last 15 minutes
        $activeSessions = DB::table('personal_access_tokens')
            ->where('last_used_at', '>', now()->subMinutes(15))
            ->count();

        // Daily active users (last 24 hours)
        $dailyActiveUsers = DB::table('personal_access_tokens')
            ->where('last_used_at', '>', now()->subDay())
            ->distinct('tokenable_id')
            ->count('tokenable_id');

        // Weekly active users (last 7 days)
        $weeklyActiveUsers = DB::table('personal_access_tokens')
            ->where('last_used_at', '>', now()->subDays(7))
            ->distinct('tokenable_id')
            ->count('tokenable_id');

        // Monthly active users (last 30 days)
        $monthlyActiveUsers = DB::table('personal_access_tokens')
            ->where('last_used_at', '>', now()->subDays(30))
            ->distinct('tokenable_id')
            ->count('tokenable_id');

        // Platform distribution
        $platformStats = DB::table('personal_access_tokens')
            ->select(DB::raw("
                SUM(CASE WHEN name LIKE '%Android%' THEN 1 ELSE 0 END) as android,
                SUM(CASE WHEN name LIKE '%iOS%' THEN 1 ELSE 0 END) as ios,
                SUM(CASE WHEN name NOT LIKE '%Android%' AND name NOT LIKE '%iOS%' THEN 1 ELSE 0 END) as other
            "))
            ->first();

        return [
            'active_sessions' => $activeSessions,
            'daily_active_users' => $dailyActiveUsers,
            'weekly_active_users' => $weeklyActiveUsers,
            'monthly_active_users' => $monthlyActiveUsers,
            'platform_distribution' => [
                'android' => (int) $platformStats->android,
                'ios' => (int) $platformStats->ios,
                'other' => (int) $platformStats->other,
            ],
        ];
    }
}
