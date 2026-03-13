<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Integration tests for complete user flows
 * Tests end-to-end scenarios across multiple components
 */
class CompleteUserFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete registration and login flow
     * 
     * @test
     */
    public function complete_registration_and_login_flow()
    {
        // Step 1: Register new user
        $registrationData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+2348012345678',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!'
        ];

        $registerResponse = $this->postJson('/api/v1/auth/register', $registrationData);
        
        $registerResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['user', 'token']
            ]);

        $userId = $registerResponse->json('data.user.id');
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'john@example.com',
            'kyc_status' => 'pending'
        ]);

        // Step 2: Login with credentials
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass123!'
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['user', 'token']
            ]);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        // Step 3: Access protected endpoint with token
        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/user/profile');

        $profileResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'john@example.com',
                    'name' => 'John Doe'
                ]
            ]);
    }

    /**
     * Test complete group creation, joining, and starting flow
     * 
     * @test
     */
    public function complete_group_creation_joining_and_starting_flow()
    {
        // Setup: Create users
        $creator = User::factory()->create(['kyc_status' => 'verified']);
        $member1 = User::factory()->create(['kyc_status' => 'verified']);
        $member2 = User::factory()->create(['kyc_status' => 'verified']);

        // Step 1: Creator creates a group
        $groupData = [
            'name' => 'Test Savings Group',
            'description' => 'Monthly savings group',
            'contribution_amount' => 5000,
            'total_members' => 3,
            'cycle_days' => 3,
            'frequency' => 'daily'
        ];

        $createResponse = $this->actingAs($creator)
            ->postJson('/api/v1/groups', $groupData);

        $createResponse->assertStatus(201);
        $groupId = $createResponse->json('data.id');
        $groupCode = $createResponse->json('data.group_code');

        $this->assertDatabaseHas('groups', [
            'id' => $groupId,
            'status' => 'pending',
            'current_members' => 1
        ]);

        // Step 2: Members join the group
        $join1Response = $this->actingAs($member1)
            ->postJson("/api/v1/groups/{$groupId}/join", [
                'group_code' => $groupCode
            ]);

        $join1Response->assertStatus(200);

        $join2Response = $this->actingAs($member2)
            ->postJson("/api/v1/groups/{$groupId}/join", [
                'group_code' => $groupCode
            ]);

        $join2Response->assertStatus(200);

        $this->assertDatabaseHas('groups', [
            'id' => $groupId,
            'current_members' => 3
        ]);

        // Step 3: Creator starts the group
        $startResponse = $this->actingAs($creator)
            ->postJson("/api/v1/groups/{$groupId}/start");

        $startResponse->assertStatus(200);

        $this->assertDatabaseHas('groups', [
            'id' => $groupId,
            'status' => 'active'
        ]);

        // Verify position assignments
        $group = Group::with('members')->find($groupId);
        $positions = $group->members->pluck('position_number')->sort()->values()->toArray();
        $this->assertEquals([1, 2, 3], $positions);

        // Verify payout schedule
        $scheduleResponse = $this->actingAs($creator)
            ->getJson("/api/v1/groups/{$groupId}/schedule");

        $scheduleResponse->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test complete contribution payment flow
     * 
     * @test
     */
    public function complete_contribution_payment_flow()
    {
        // Setup: Create active group with member
        $user = User::factory()->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 10000
        ]);
        
        $group = Group::factory()->create([
            'status' => 'active',
            'contribution_amount' => 5000,
            'start_date' => now()->toDateString()
        ]);

        $group->members()->create([
            'user_id' => $user->id,
            'position_number' => 1,
            'payout_day' => 1
        ]);

        // Step 1: Initiate contribution payment
        $contributionData = [
            'group_id' => $group->id,
            'amount' => 5000,
            'payment_method' => 'wallet'
        ];

        $paymentResponse = $this->actingAs($user)
            ->postJson('/api/v1/contributions', $contributionData);

        $paymentResponse->assertStatus(201);
        $paymentRef = $paymentResponse->json('data.payment_reference');

        $this->assertDatabaseHas('contributions', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_reference' => $paymentRef,
            'payment_status' => 'pending'
        ]);

        // Step 2: Simulate webhook verification
        $webhookData = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $paymentRef,
                'amount' => 500000, // Amount in kobo
                'status' => 'success'
            ]
        ];

        $webhookResponse = $this->postJson('/api/v1/webhooks/paystack', $webhookData);
        $webhookResponse->assertStatus(200);

        // Step 3: Verify contribution recorded
        $this->assertDatabaseHas('contributions', [
            'payment_reference' => $paymentRef,
            'payment_status' => 'successful'
        ]);

        // Step 4: Verify wallet debited
        $user->refresh();
        $this->assertEquals(5000, $user->wallet_balance);

        // Step 5: Verify transaction history
        $historyResponse = $this->actingAs($user)
            ->getJson('/api/v1/contributions/history');

        $historyResponse->assertStatus(200)
            ->assertJsonFragment([
                'payment_reference' => $paymentRef,
                'payment_status' => 'successful'
            ]);
    }

    /**
     * Test complete payout processing flow
     * 
     * @test
     */
    public function complete_payout_processing_flow()
    {
        // Setup: Create active group with all contributions made
        $group = Group::factory()->create([
            'status' => 'active',
            'contribution_amount' => 1000,
            'total_members' => 3,
            'cycle_days' => 3,
            'start_date' => now()->toDateString()
        ]);

        $users = User::factory()->count(3)->create(['kyc_status' => 'verified']);
        
        foreach ($users as $index => $user) {
            $group->members()->create([
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'payout_day' => $index + 1
            ]);

            // All members contribute for day 1
            $group->contributions()->create([
                'user_id' => $user->id,
                'amount' => 1000,
                'payment_method' => 'wallet',
                'payment_reference' => 'PAY-' . $user->id . '-' . now()->timestamp,
                'payment_status' => 'successful',
                'contribution_date' => now()->toDateString(),
                'paid_at' => now()
            ]);
        }

        // Step 1: Process payout (simulating scheduler)
        $payoutService = app(\App\Services\PayoutService::class);
        $payout = $payoutService->calculateDailyPayout($group->id, now());

        $this->assertNotNull($payout);
        $this->assertEquals(3000, $payout->amount); // 3 members × 1000

        // Step 2: Verify payout created
        $this->assertDatabaseHas('payouts', [
            'group_id' => $group->id,
            'user_id' => $users[0]->id, // First member gets payout
            'amount' => 3000,
            'status' => 'pending'
        ]);

        // Step 3: Process the payout
        $result = $payoutService->processPayout($payout->id);
        $this->assertTrue($result);

        // Step 4: Verify payout completed
        $payout->refresh();
        $this->assertEquals('successful', $payout->status);

        // Step 5: Verify wallet credited
        $recipient = $users[0];
        $recipient->refresh();
        $this->assertEquals(3000, $recipient->wallet_balance);

        // Step 6: Verify member marked as received payout
        $member = $group->members()->where('user_id', $recipient->id)->first();
        $this->assertTrue($member->has_received_payout);
    }

    /**
     * Test complete wallet funding and withdrawal flow
     * 
     * @test
     */
    public function complete_wallet_funding_and_withdrawal_flow()
    {
        // Setup: Create user with bank account
        $user = User::factory()->create(['kyc_status' => 'verified']);
        $bankAccount = BankAccount::factory()->create([
            'user_id' => $user->id,
            'is_verified' => true,
            'is_primary' => true
        ]);

        // Step 1: Fund wallet
        $fundingData = [
            'amount' => 10000,
            'payment_method' => 'card'
        ];

        $fundingResponse = $this->actingAs($user)
            ->postJson('/api/v1/wallet/fund', $fundingData);

        $fundingResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['authorization_url', 'reference']
            ]);

        $fundingRef = $fundingResponse->json('data.reference');

        // Step 2: Simulate successful payment webhook
        $webhookData = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $fundingRef,
                'amount' => 1000000, // 10000 in kobo
                'status' => 'success'
            ]
        ];

        $this->postJson('/api/v1/webhooks/paystack', $webhookData);

        // Step 3: Verify wallet funded
        $user->refresh();
        $this->assertEquals(10000, $user->wallet_balance);

        // Step 4: Check balance
        $balanceResponse = $this->actingAs($user)
            ->getJson('/api/v1/wallet/balance');

        $balanceResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['balance' => 10000]
            ]);

        // Step 5: Initiate withdrawal
        $withdrawalData = [
            'amount' => 5000,
            'bank_account_id' => $bankAccount->id
        ];

        $withdrawalResponse = $this->actingAs($user)
            ->postJson('/api/v1/wallet/withdraw', $withdrawalData);

        $withdrawalResponse->assertStatus(200);
        $withdrawalId = $withdrawalResponse->json('data.id');

        // Step 6: Verify withdrawal pending
        $this->assertDatabaseHas('withdrawals', [
            'id' => $withdrawalId,
            'user_id' => $user->id,
            'amount' => 5000,
            'status' => 'pending'
        ]);

        // Step 7: Admin approves withdrawal
        $admin = User::factory()->create(['is_admin' => true]);
        
        $approvalResponse = $this->actingAs($admin)
            ->postJson("/api/v1/admin/withdrawals/{$withdrawalId}/approve");

        $approvalResponse->assertStatus(200);

        // Step 8: Verify withdrawal processed
        $this->assertDatabaseHas('withdrawals', [
            'id' => $withdrawalId,
            'status' => 'successful'
        ]);

        // Step 9: Verify wallet debited
        $user->refresh();
        $this->assertEquals(5000, $user->wallet_balance);

        // Step 10: Check transaction history
        $transactionsResponse = $this->actingAs($user)
            ->getJson('/api/v1/wallet/transactions');

        $transactionsResponse->assertStatus(200)
            ->assertJsonCount(2, 'data'); // Funding + Withdrawal
    }
}
