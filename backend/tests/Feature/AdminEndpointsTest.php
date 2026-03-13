<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate as admin
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->admin);
    }

    /**
     * Test admin can get dashboard statistics.
     */
    public function test_admin_can_get_dashboard_statistics(): void
    {
        // Create some test data
        User::factory()->count(5)->create(['status' => 'active']);
        User::factory()->count(2)->create(['status' => 'suspended']);
        Group::factory()->count(3)->create(['status' => 'active']);

        // Get dashboard stats
        $response = $this->getJson('/api/v1/admin/dashboard/stats');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'users' => [
                        'total',
                        'active',
                        'suspended',
                        'kyc_pending',
                    ],
                    'groups' => [
                        'total',
                        'active',
                        'completed',
                    ],
                    'transactions',
                    'system',
                ],
            ]);
    }

    /**
     * Test admin can list all users.
     */
    public function test_admin_can_list_all_users(): void
    {
        // Create test users
        User::factory()->count(10)->create();

        // List users
        $response = $this->getJson('/api/v1/admin/users');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * Test admin can filter users by status.
     */
    public function test_admin_can_filter_users_by_status(): void
    {
        // Create users with different statuses
        User::factory()->count(3)->create(['status' => 'active']);
        User::factory()->count(2)->create(['status' => 'suspended']);

        // Filter by active status
        $response = $this->getJson('/api/v1/admin/users?status=active');

        // Assert filtered results
        $response->assertStatus(200);
        $users = $response->json('data.data');
        
        foreach ($users as $user) {
            $this->assertEquals('active', $user['status']);
        }
    }

    /**
     * Test admin can get user details.
     */
    public function test_admin_can_get_user_details(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Get user details
        $response = $this->getJson("/api/v1/admin/users/{$user->id}");

        // Assert successful response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    /**
     * Test admin can suspend a user.
     */
    public function test_admin_can_suspend_user(): void
    {
        // Create a user
        $user = User::factory()->create(['status' => 'active']);

        // Suspend the user
        $response = $this->putJson("/api/v1/admin/users/{$user->id}/suspend", [
            'reason' => 'Suspicious activity detected',
        ]);

        // Assert successful suspension
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User suspended successfully',
            ]);

        // Verify user is suspended in database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'suspended',
        ]);
    }

    /**
     * Test admin cannot suspend another admin.
     */
    public function test_admin_cannot_suspend_another_admin(): void
    {
        // Create another admin
        $otherAdmin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Attempt to suspend the admin
        $response = $this->putJson("/api/v1/admin/users/{$otherAdmin->id}/suspend", [
            'reason' => 'Test reason',
        ]);

        // Assert error response
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot suspend admin users',
            ]);
    }

    /**
     * Test admin can activate a user.
     */
    public function test_admin_can_activate_user(): void
    {
        // Create a suspended user
        $user = User::factory()->create(['status' => 'suspended']);

        // Activate the user
        $response = $this->putJson("/api/v1/admin/users/{$user->id}/activate");

        // Assert successful activation
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User activated successfully',
            ]);

        // Verify user is active in database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test admin can approve KYC.
     */
    public function test_admin_can_approve_kyc(): void
    {
        // Create a user with pending KYC
        $user = User::factory()->create(['kyc_status' => 'pending']);

        // Approve KYC
        $response = $this->postJson("/api/v1/admin/kyc/{$user->id}/approve");

        // Assert successful approval
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'KYC approved successfully',
            ]);

        // Verify KYC is verified in database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'kyc_status' => 'verified',
        ]);
    }

    /**
     * Test admin can reject KYC.
     */
    public function test_admin_can_reject_kyc(): void
    {
        // Create a user with pending KYC
        $user = User::factory()->create(['kyc_status' => 'pending']);

        // Reject KYC
        $response = $this->postJson("/api/v1/admin/kyc/{$user->id}/reject", [
            'reason' => 'Document not clear',
        ]);

        // Assert successful rejection
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'KYC rejected successfully',
            ]);

        // Verify KYC is rejected in database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'kyc_status' => 'rejected',
            'kyc_rejection_reason' => 'Document not clear',
        ]);
    }

    /**
     * Test admin can list all groups.
     */
    public function test_admin_can_list_all_groups(): void
    {
        // Create test groups
        Group::factory()->count(5)->create();

        // List groups
        $response = $this->getJson('/api/v1/admin/groups');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * Test admin can get group details.
     */
    public function test_admin_can_get_group_details(): void
    {
        // Create a group
        $group = Group::factory()->create();

        // Get group details
        $response = $this->getJson("/api/v1/admin/groups/{$group->id}");

        // Assert successful response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                ],
            ]);
    }

    /**
     * Test admin can list pending withdrawals.
     */
    public function test_admin_can_list_pending_withdrawals(): void
    {
        // Create pending withdrawals
        Withdrawal::factory()->count(3)->create([
            'admin_approval_status' => 'pending',
        ]);

        // List pending withdrawals
        $response = $this->getJson('/api/v1/admin/withdrawals/pending');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * Test admin can approve withdrawal.
     */
    public function test_admin_can_approve_withdrawal(): void
    {
        // Create a pending withdrawal
        $withdrawal = Withdrawal::factory()->create([
            'admin_approval_status' => 'pending',
        ]);

        // Approve withdrawal
        $response = $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/approve");

        // Assert successful approval
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Withdrawal approved successfully',
            ]);

        // Verify withdrawal is approved in database
        $this->assertDatabaseHas('withdrawals', [
            'id' => $withdrawal->id,
            'admin_approval_status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);
    }

    /**
     * Test admin can reject withdrawal.
     */
    public function test_admin_can_reject_withdrawal(): void
    {
        // Create a pending withdrawal
        $withdrawal = Withdrawal::factory()->create([
            'admin_approval_status' => 'pending',
        ]);

        // Reject withdrawal
        $response = $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/reject", [
            'reason' => 'Insufficient verification',
        ]);

        // Assert successful rejection
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Withdrawal rejected successfully',
            ]);

        // Verify withdrawal is rejected in database
        $this->assertDatabaseHas('withdrawals', [
            'id' => $withdrawal->id,
            'admin_approval_status' => 'rejected',
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient verification',
        ]);
    }

    /**
     * Test suspension requires reason.
     */
    public function test_suspension_requires_reason(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Attempt to suspend without reason
        $response = $this->putJson("/api/v1/admin/users/{$user->id}/suspend", []);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /**
     * Test KYC rejection requires reason.
     */
    public function test_kyc_rejection_requires_reason(): void
    {
        // Create a user
        $user = User::factory()->create(['kyc_status' => 'pending']);

        // Attempt to reject without reason
        $response = $this->postJson("/api/v1/admin/kyc/{$user->id}/reject", []);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /**
     * Test admin can get user analytics.
     */
    public function test_admin_can_get_user_analytics(): void
    {
        // Create test users
        User::factory()->count(10)->create();

        // Get user analytics
        $response = $this->getJson('/api/v1/admin/analytics/users');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'user_growth',
                    'new_users',
                    'active_users',
                    'total_users',
                    'retention_rate',
                    'kyc_verification_rate',
                ],
            ]);
    }

    /**
     * Test admin can get user analytics with date range.
     */
    public function test_admin_can_get_user_analytics_with_date_range(): void
    {
        // Create test users
        User::factory()->count(5)->create();

        // Get user analytics with date range
        $response = $this->getJson('/api/v1/admin/analytics/users?start_date=2024-01-01&end_date=2024-12-31');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => '2024-01-01',
                        'end_date' => '2024-12-31',
                    ],
                ],
            ]);
    }

    /**
     * Test admin can get group analytics.
     */
    public function test_admin_can_get_group_analytics(): void
    {
        // Create test groups
        Group::factory()->count(5)->create();

        // Get group analytics
        $response = $this->getJson('/api/v1/admin/analytics/groups');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'group_creation',
                    'groups_started',
                    'groups_completed',
                    'completion_rate',
                    'average_group_size',
                    'average_contribution_amount',
                    'groups_by_status',
                ],
            ]);
    }

    /**
     * Test admin can get transaction analytics.
     */
    public function test_admin_can_get_transaction_analytics(): void
    {
        // Get transaction analytics
        $response = $this->getJson('/api/v1/admin/analytics/transactions');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'contribution_trends',
                    'payout_trends',
                    'withdrawal_trends',
                    'contribution_success_rate',
                    'payout_success_rate',
                    'total_contribution_volume',
                    'total_payout_volume',
                    'total_withdrawal_volume',
                    'total_transaction_volume',
                ],
            ]);
    }

    /**
     * Test admin can get revenue analytics.
     */
    public function test_admin_can_get_revenue_analytics(): void
    {
        // Get revenue analytics
        $response = $this->getJson('/api/v1/admin/analytics/revenue');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'total_revenue',
                    'funding_fees',
                    'withdrawal_fees',
                    'wallet_funding_volume',
                    'withdrawal_volume',
                    'daily_revenue',
                    'active_users',
                    'revenue_per_user',
                ],
            ]);
    }

    /**
     * Test analytics date validation.
     */
    public function test_analytics_date_validation(): void
    {
        // Test with invalid date range (end before start)
        $response = $this->getJson('/api/v1/admin/analytics/users?start_date=2024-12-31&end_date=2024-01-01');

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    /**
     * Test analytics export to JSON.
     */
    public function test_analytics_export_to_json(): void
    {
        // Create test data
        User::factory()->count(5)->create();

        // Get analytics with JSON export
        $response = $this->getJson('/api/v1/admin/analytics/users?export=json');

        // Assert successful response with JSON format
        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'user_growth',
                'new_users',
                'active_users',
                'total_users',
                'retention_rate',
                'kyc_verification_rate',
            ]);
    }

    /**
     * Test analytics export to CSV.
     */
    public function test_analytics_export_to_csv(): void
    {
        // Create test data
        User::factory()->count(5)->create();

        // Get analytics with CSV export
        $response = $this->getJson('/api/v1/admin/analytics/users?export=csv');

        // Assert successful response with CSV headers
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
