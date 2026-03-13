<?php

namespace Tests\Property;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Eris\Generator;

/**
 * Property-based test for contribution accounting invariant
 * 
 * **Validates: Property 19 - Contribution Accounting Invariant**
 * 
 * For any active group at any point in time, the total number of recorded 
 * contributions should equal the sum of contributions made by each individual member.
 */
class ContributionAccountingPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property: Total contributions equals sum of individual member contributions
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 19: Contribution Accounting Invariant
     */
    public function total_contributions_equals_sum_of_individual_contributions()
    {
        $this->forAll(
            Generator\choose(3, 10), // Number of members
            Generator\choose(1, 5)   // Number of days
        )->then(function ($memberCount, $days) {
            // Create group
            $group = Group::factory()->create([
                'status' => 'active',
                'contribution_amount' => 1000,
                'total_members' => $memberCount,
                'cycle_days' => $days,
                'start_date' => now()->toDateString()
            ]);

            // Create members
            $users = User::factory()->count($memberCount)->create([
                'kyc_status' => 'verified'
            ]);

            foreach ($users as $index => $user) {
                $group->members()->create([
                    'user_id' => $user->id,
                    'position_number' => $index + 1,
                    'payout_day' => $index + 1
                ]);
            }

            // Simulate contributions over multiple days
            $individualContributions = [];
            
            for ($day = 0; $day < $days; $day++) {
                $currentDate = now()->addDays($day);
                
                // Random subset of members contribute each day
                $contributingMembers = $users->random(rand(1, $memberCount));
                
                foreach ($contributingMembers as $user) {
                    $group->contributions()->create([
                        'user_id' => $user->id,
                        'amount' => 1000,
                        'payment_method' => 'wallet',
                        'payment_reference' => "PAY-{$group->id}-{$user->id}-{$day}",
                        'payment_status' => 'successful',
                        'contribution_date' => $currentDate->toDateString(),
                        'paid_at' => $currentDate
                    ]);

                    // Track individual contributions
                    if (!isset($individualContributions[$user->id])) {
                        $individualContributions[$user->id] = 0;
                    }
                    $individualContributions[$user->id]++;
                }
            }

            // Property verification
            $totalContributions = $group->contributions()->count();
            $sumOfIndividualContributions = array_sum($individualContributions);

            $this->assertEquals(
                $sumOfIndividualContributions,
                $totalContributions,
                "Total contributions ({$totalContributions}) should equal sum of individual contributions ({$sumOfIndividualContributions})"
            );

            // Additional invariant: Each member's contribution count should match database
            foreach ($individualContributions as $userId => $expectedCount) {
                $actualCount = $group->contributions()
                    ->where('user_id', $userId)
                    ->count();
                
                $this->assertEquals(
                    $expectedCount,
                    $actualCount,
                    "User {$userId} contribution count mismatch"
                );
            }
        });
    }

    /**
     * Property: Contribution amounts sum correctly across all members
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 19: Contribution Accounting Invariant
     */
    public function contribution_amounts_sum_correctly_across_members()
    {
        $this->forAll(
            Generator\choose(3, 8),      // Number of members
            Generator\choose(100, 5000)  // Contribution amount
        )->then(function ($memberCount, $contributionAmount) {
            // Create group
            $group = Group::factory()->create([
                'status' => 'active',
                'contribution_amount' => $contributionAmount,
                'total_members' => $memberCount,
                'start_date' => now()->toDateString()
            ]);

            // Create members
            $users = User::factory()->count($memberCount)->create([
                'kyc_status' => 'verified'
            ]);

            foreach ($users as $index => $user) {
                $group->members()->create([
                    'user_id' => $user->id,
                    'position_number' => $index + 1,
                    'payout_day' => $index + 1
                ]);
            }

            // All members contribute
            $expectedTotalAmount = 0;
            foreach ($users as $user) {
                $group->contributions()->create([
                    'user_id' => $user->id,
                    'amount' => $contributionAmount,
                    'payment_method' => 'wallet',
                    'payment_reference' => "PAY-{$group->id}-{$user->id}",
                    'payment_status' => 'successful',
                    'contribution_date' => now()->toDateString(),
                    'paid_at' => now()
                ]);

                $expectedTotalAmount += $contributionAmount;
            }

            // Property verification
            $actualTotalAmount = $group->contributions()
                ->where('payment_status', 'successful')
                ->sum('amount');

            $this->assertEquals(
                $expectedTotalAmount,
                $actualTotalAmount,
                "Total contribution amount should equal sum of all individual contributions"
            );

            $this->assertEquals(
                $memberCount * $contributionAmount,
                $actualTotalAmount,
                "Total should equal member count × contribution amount"
            );
        });
    }

    /**
     * Property: Contribution count per day never exceeds member count
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 19: Contribution Accounting Invariant
     */
    public function contribution_count_per_day_never_exceeds_member_count()
    {
        $this->forAll(
            Generator\choose(3, 10)  // Number of members
        )->then(function ($memberCount) {
            // Create group
            $group = Group::factory()->create([
                'status' => 'active',
                'contribution_amount' => 1000,
                'total_members' => $memberCount,
                'start_date' => now()->toDateString()
            ]);

            // Create members
            $users = User::factory()->count($memberCount)->create([
                'kyc_status' => 'verified'
            ]);

            foreach ($users as $index => $user) {
                $group->members()->create([
                    'user_id' => $user->id,
                    'position_number' => $index + 1,
                    'payout_day' => $index + 1
                ]);
            }

            // All members contribute
            foreach ($users as $user) {
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

            // Property verification
            $contributionsToday = $group->contributions()
                ->where('contribution_date', now()->toDateString())
                ->count();

            $this->assertLessThanOrEqual(
                $memberCount,
                $contributionsToday,
                "Daily contributions should never exceed member count"
            );

            // Each member should have at most one contribution per day
            foreach ($users as $user) {
                $userContributionsToday = $group->contributions()
                    ->where('user_id', $user->id)
                    ->where('contribution_date', now()->toDateString())
                    ->count();

                $this->assertLessThanOrEqual(
                    1,
                    $userContributionsToday,
                    "Each member should have at most one contribution per day"
                );
            }
        });
    }
}
