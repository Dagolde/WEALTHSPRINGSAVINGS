<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Integration tests for multi-user scenarios and concurrent operations
 */
class MultiUserScenarioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test multiple users contributing to same group concurrently
     * 
     * @test
     */
    public function multiple_users_can_contribute_to_group_concurrently()
    {
        // Setup: Create active group with multiple members
        $group = Group::factory()->create([
            'status' => 'active',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'start_date' => now()->toDateString()
        ]);

        $users = User::factory()->count(5)->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 5000
        ]);

        foreach ($users as $index => $user) {
            $group->members()->create([
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'payout_day' => $index + 1
            ]);
        }

        // Simulate concurrent contributions
        $contributions = [];
        foreach ($users as $user) {
            $response = $this->actingAs($user)
                ->postJson('/api/v1/contributions', [
                    'group_id' => $group->id,
                    'amount' => 1000,
                    'payment_method' => 'wallet'
                ]);

            $response->assertStatus(201);
            $contributions[] = $response->json('data');
        }

        // Verify all contributions recorded
        $this->assertCount(5, $contributions);
        $this->assertEquals(5, $group->contributions()->count());

        // Verify each user's wallet debited
        foreach ($users as $user) {
            $user->refresh();
            $this->assertEquals(4000, $user->wallet_balance);
        }
    }

    /**
     * Test concurrent contribution attempts by same user (should fail)
     * 
     * @test
     */
    public function concurrent_contribution_attempts_by_same_user_should_fail()
    {
        // Setup
        $user = User::factory()->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 10000
        ]);

        $group = Group::factory()->create([
            'status' => 'active',
            'contribution_amount' => 1000,
            'start_date' => now()->toDateString()
        ]);

        $group->members()->create([
            'user_id' => $user->id,
            'position_number' => 1,
            'payout_day' => 1
        ]);

        // First contribution should succeed
        $response1 = $this->actingAs($user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $group->id,
                'amount' => 1000,
                'payment_method' => 'wallet'
            ]);

        $response1->assertStatus(201);

        // Second contribution same day should fail
        $response2 = $this->actingAs($user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $group->id,
                'amount' => 1000,
                'payment_method' => 'wallet'
            ]);

        $response2->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'You have already contributed to this group today'
            ]);

        // Verify only one contribution recorded
        $this->assertEquals(1, $group->contributions()->count());
    }

    /**
     * Test multiple groups running concurrently
     * 
     * @test
     */
    public function multiple_groups_can_run_concurrently()
    {
        // Create 3 different groups
        $groups = Group::factory()->count(3)->create([
            'status' => 'active',
            'contribution_amount' => 1000,
            'total_members' => 3,
            'start_date' => now()->toDateString()
        ]);

        // Create users participating in multiple groups
        $users = User::factory()->count(5)->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 10000
        ]);

        // Assign members to groups (some users in multiple groups)
        foreach ($groups as $groupIndex => $group) {
            for ($i = 0; $i < 3; $i++) {
                $userIndex = ($groupIndex + $i) % 5;
                $group->members()->create([
                    'user_id' => $users[$userIndex]->id,
                    'position_number' => $i + 1,
                    'payout_day' => $i + 1
                ]);
            }
        }

        // Each user contributes to their groups
        foreach ($users as $user) {
            $userGroups = Group::whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->get();

            foreach ($userGroups as $group) {
                $response = $this->actingAs($user)
                    ->postJson('/api/v1/contributions', [
                        'group_id' => $group->id,
                        'amount' => 1000,
                        'payment_method' => 'wallet'
                    ]);

                $response->assertStatus(201);
            }
        }

        // Verify contributions recorded correctly
        foreach ($groups as $group) {
            $this->assertEquals(3, $group->contributions()->count());
        }

        // Verify total contributions
        $totalContributions = DB::table('contributions')
            ->where('contribution_date', now()->toDateString())
            ->count();
        $this->assertEquals(9, $totalContributions); // 3 groups × 3 members
    }

    /**
     * Test payout processing with multiple eligible groups
     * 
     * @test
     */
    public function payout_processing_handles_multiple_eligible_groups()
    {
        $payoutService = app(\App\Services\PayoutService::class);

        // Create 3 groups with all contributions made
        $groups = [];
        for ($i = 0; $i < 3; $i++) {
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

                // All members contribute
                $group->contributions()->create([
                    'user_id' => $user->id,
                    'amount' => 1000,
                    'payment_method' => 'wallet',
                    'payment_reference' => "PAY-{$group->id}-{$user->id}",
                    'payment_status' => 'successful',
                    'contribution_date' => now()->toDateString(),
                    'paid_at' => now()
                ]);
            }

            $groups[] = $group;
        }

        // Process payouts for all groups
        foreach ($groups as $group) {
            $payout = $payoutService->calculateDailyPayout($group->id, now());
            $this->assertNotNull($payout);
            $this->assertEquals(3000, $payout->amount);

            $result = $payoutService->processPayout($payout->id);
            $this->assertTrue($result);
        }

        // Verify all payouts processed
        $totalPayouts = DB::table('payouts')
            ->where('payout_date', now()->toDateString())
            ->where('status', 'successful')
            ->count();
        $this->assertEquals(3, $totalPayouts);
    }

    /**
     * Test complete group cycle with all members
     * 
     * @test
     */
    public function complete_group_cycle_with_all_members()
    {
        $payoutService = app(\App\Services\PayoutService::class);

        // Create group with 3 members
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
        }

        // Simulate 3-day cycle
        for ($day = 0; $day < 3; $day++) {
            $currentDate = now()->addDays($day);

            // All members contribute
            foreach ($users as $user) {
                $group->contributions()->create([
                    'user_id' => $user->id,
                    'amount' => 1000,
                    'payment_method' => 'wallet',
                    'payment_reference' => "PAY-{$group->id}-{$user->id}-DAY{$day}",
                    'payment_status' => 'successful',
                    'contribution_date' => $currentDate->toDateString(),
                    'paid_at' => $currentDate
                ]);
            }

            // Process payout for the day
            $payout = $payoutService->calculateDailyPayout($group->id, $currentDate);
            $this->assertNotNull($payout);
            $payoutService->processPayout($payout->id);
        }

        // Verify cycle completion
        $group->refresh();
        
        // All members should have received payout
        $membersWithPayout = $group->members()
            ->where('has_received_payout', true)
            ->count();
        $this->assertEquals(3, $membersWithPayout);

        // Total contributions should equal total payouts
        $totalContributions = $group->contributions()
            ->where('payment_status', 'successful')
            ->sum('amount');
        $totalPayouts = $group->payouts()
            ->where('status', 'successful')
            ->sum('amount');
        
        $this->assertEquals($totalContributions, $totalPayouts);
        $this->assertEquals(9000, $totalContributions); // 3 members × 3 days × 1000
    }

    /**
     * Test handling missed contributions in multi-user scenario
     * 
     * @test
     */
    public function handling_missed_contributions_in_multi_user_scenario()
    {
        $payoutService = app(\App\Services\PayoutService::class);

        // Create group
        $group = Group::factory()->create([
            'status' => 'active',
            'contribution_amount' => 1000,
            'total_members' => 3,
            'start_date' => now()->toDateString()
        ]);

        $users = User::factory()->count(3)->create(['kyc_status' => 'verified']);

        foreach ($users as $index => $user) {
            $group->members()->create([
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'payout_day' => $index + 1
            ]);
        }

        // Only 2 out of 3 members contribute
        for ($i = 0; $i < 2; $i++) {
            $group->contributions()->create([
                'user_id' => $users[$i]->id,
                'amount' => 1000,
                'payment_method' => 'wallet',
                'payment_reference' => "PAY-{$users[$i]->id}",
                'payment_status' => 'successful',
                'contribution_date' => now()->toDateString(),
                'paid_at' => now()
            ]);
        }

        // Payout should not be processed (missing contribution)
        $payout = $payoutService->calculateDailyPayout($group->id, now());
        $this->assertNull($payout);

        // Verify no payout created
        $this->assertEquals(0, $group->payouts()->count());

        // Check missed contributions
        $contributionService = app(\App\Services\ContributionService::class);
        $missedContributions = $contributionService->getMissedContributions(
            $users[2]->id,
            $group->id
        );

        $this->assertCount(1, $missedContributions);
    }

    /**
     * Test wallet balance consistency across concurrent operations
     * 
     * @test
     */
    public function wallet_balance_consistency_across_concurrent_operations()
    {
        $user = User::factory()->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 10000
        ]);

        $walletService = app(\App\Services\WalletService::class);

        // Simulate concurrent wallet operations
        DB::transaction(function () use ($user, $walletService) {
            // Multiple debits
            $walletService->debitWallet($user->id, 1000, 'Test debit 1');
            $walletService->debitWallet($user->id, 2000, 'Test debit 2');
            $walletService->debitWallet($user->id, 1500, 'Test debit 3');

            // Multiple credits
            $walletService->creditWallet($user->id, 500, 'Test credit 1');
            $walletService->creditWallet($user->id, 1000, 'Test credit 2');
        });

        // Verify final balance
        $user->refresh();
        $expectedBalance = 10000 - 1000 - 2000 - 1500 + 500 + 1000;
        $this->assertEquals($expectedBalance, $user->wallet_balance);

        // Verify transaction audit trail
        $transactions = DB::table('wallet_transactions')
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(5, $transactions);

        // Verify balance progression
        $runningBalance = 10000;
        foreach ($transactions as $transaction) {
            $this->assertEquals($runningBalance, $transaction->balance_before);
            
            if ($transaction->type === 'debit') {
                $runningBalance -= $transaction->amount;
            } else {
                $runningBalance += $transaction->amount;
            }
            
            $this->assertEquals($runningBalance, $transaction->balance_after);
        }
    }
}
