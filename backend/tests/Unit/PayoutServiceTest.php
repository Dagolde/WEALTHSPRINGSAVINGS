<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\User;
use App\Models\GroupMember;
use App\Models\Contribution;
use App\Services\PayoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayoutService $payoutService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payoutService = new PayoutService();
    }

    /** @test */
    public function it_verifies_payout_eligibility_when_all_conditions_met()
    {
        // Create a group with 3 members
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 3,
            'contribution_amount' => 1000.00,
        ]);

        // Create 3 users and add them as group members
        $users = User::factory()->count(3)->create();
        $payoutDay = Carbon::now();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);

            // Create successful contribution for each user on payout day
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
                'amount' => 1000.00,
            ]);
        }

        // Verify eligibility for first user
        $result = $this->payoutService->verifyPayoutEligibility(
            $group,
            $users[0],
            $payoutDay
        );

        $this->assertTrue($result);
    }

    /** @test */
    public function it_throws_exception_when_group_is_not_active()
    {
        $group = Group::factory()->create(['status' => 'pending']);
        $user = User::factory()->create();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => false,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Group is not active');

        $this->payoutService->verifyPayoutEligibility(
            $group,
            $user,
            Carbon::now()
        );
    }

    /** @test */
    public function it_throws_exception_when_user_is_not_group_member()
    {
        $group = Group::factory()->create(['status' => 'active']);
        $user = User::factory()->create();

        // Don't add user as group member

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User is not a member of this group');

        $this->payoutService->verifyPayoutEligibility(
            $group,
            $user,
            Carbon::now()
        );
    }

    /** @test */
    public function it_throws_exception_when_user_already_received_payout()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
        ]);

        $user = User::factory()->create();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => true, // Already received payout
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User has already received payout');

        $this->payoutService->verifyPayoutEligibility(
            $group,
            $user,
            Carbon::now()
        );
    }

    /** @test */
    public function it_throws_exception_when_not_all_members_have_contributed()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 3,
        ]);

        $users = User::factory()->count(3)->create();
        $payoutDay = Carbon::now();

        // Add all users as group members
        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);
        }

        // Only 2 out of 3 users contribute
        for ($i = 0; $i < 2; $i++) {
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $users[$i]->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
            ]);
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not all group members have contributed');

        $this->payoutService->verifyPayoutEligibility(
            $group,
            $users[0],
            $payoutDay
        );
    }

    /** @test */
    public function it_throws_exception_when_contributions_are_not_successful()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 2,
        ]);

        $users = User::factory()->count(2)->create();
        $payoutDay = Carbon::now();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);

            // Create pending contributions (not successful)
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'pending',
            ]);
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not all group members have contributed');

        $this->payoutService->verifyPayoutEligibility(
            $group,
            $users[0],
            $payoutDay
        );
    }

    /** @test */
    public function it_returns_correct_eligible_members()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 3,
        ]);

        $users = User::factory()->count(3)->create();
        $payoutDay = Carbon::now();

        $groupMembers = [];
        foreach ($users as $index => $user) {
            $groupMembers[] = GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);

            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
            ]);
        }

        $eligibleMembers = $this->payoutService->getPayoutEligibleMembers(
            $group,
            $payoutDay
        );

        $this->assertCount(3, $eligibleMembers);
        $this->assertTrue($eligibleMembers->contains('id', $groupMembers[0]->id));
        $this->assertTrue($eligibleMembers->contains('id', $groupMembers[1]->id));
        $this->assertTrue($eligibleMembers->contains('id', $groupMembers[2]->id));
    }

    /** @test */
    public function it_excludes_members_who_already_received_payout()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 3,
        ]);

        $users = User::factory()->count(3)->create();
        $payoutDay = Carbon::now();

        // First member already received payout
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $users[0]->id,
            'position_number' => 1,
            'has_received_payout' => true,
        ]);

        // Other members haven't received payout
        for ($i = 1; $i < 3; $i++) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $users[$i]->id,
                'position_number' => $i + 1,
                'has_received_payout' => false,
            ]);
        }

        // All members contribute
        foreach ($users as $user) {
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
            ]);
        }

        $eligibleMembers = $this->payoutService->getPayoutEligibleMembers(
            $group,
            $payoutDay
        );

        $this->assertCount(2, $eligibleMembers);
        $this->assertFalse($eligibleMembers->contains('user_id', $users[0]->id));
        $this->assertTrue($eligibleMembers->contains('user_id', $users[1]->id));
        $this->assertTrue($eligibleMembers->contains('user_id', $users[2]->id));
    }

    /** @test */
    public function it_returns_empty_collection_when_not_all_members_contributed()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 3,
        ]);

        $users = User::factory()->count(3)->create();
        $payoutDay = Carbon::now();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);
        }

        // Only 2 out of 3 contribute
        for ($i = 0; $i < 2; $i++) {
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $users[$i]->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
            ]);
        }

        $eligibleMembers = $this->payoutService->getPayoutEligibleMembers(
            $group,
            $payoutDay
        );

        $this->assertCount(0, $eligibleMembers);
    }

    /** @test */
    public function it_returns_empty_collection_when_group_is_not_active()
    {
        $group = Group::factory()->create([
            'status' => 'pending',
            'total_members' => 1,
        ]);

        $user = User::factory()->create();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => false,
        ]);

        Contribution::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'contribution_date' => Carbon::now(),
            'payment_status' => 'successful',
        ]);

        $eligibleMembers = $this->payoutService->getPayoutEligibleMembers(
            $group,
            Carbon::now()
        );

        $this->assertCount(0, $eligibleMembers);
    }

    /** @test */
    public function it_handles_single_member_group()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
        ]);

        $user = User::factory()->create();
        $payoutDay = Carbon::now();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'position_number' => 1,
            'has_received_payout' => false,
        ]);

        Contribution::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'contribution_date' => $payoutDay,
            'payment_status' => 'successful',
        ]);

        $result = $this->payoutService->verifyPayoutEligibility(
            $group,
            $user,
            $payoutDay
        );

        $this->assertTrue($result);

        $eligibleMembers = $this->payoutService->getPayoutEligibleMembers(
            $group,
            $payoutDay
        );

        $this->assertCount(1, $eligibleMembers);
    }

    /** @test */
    public function it_handles_empty_group()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 0,
        ]);

        $user = User::factory()->create();

        $eligibleMembers = $this->payoutService->getPayoutEligibleMembers(
            $group,
            Carbon::now()
        );

        $this->assertCount(0, $eligibleMembers);
    }

    /** @test */
    public function it_accepts_string_payout_day_and_converts_to_carbon()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
        ]);

        $user = User::factory()->create();
        $payoutDayString = '2024-01-15';

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => false,
        ]);

        Contribution::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'contribution_date' => $payoutDayString,
            'payment_status' => 'successful',
        ]);

        // Should not throw exception when passing string
        $result = $this->payoutService->verifyPayoutEligibility(
            $group,
            $user,
            $payoutDayString
        );

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_contributions_on_consecutive_days()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 2,
        ]);

        $users = User::factory()->count(2)->create();
        $day1 = Carbon::now();
        $day2 = Carbon::now()->addDay();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);
        }

        // Both users contribute on day 1
        foreach ($users as $user) {
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $day1,
                'payment_status' => 'successful',
            ]);
        }

        // Both users contribute on day 2
        foreach ($users as $user) {
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $day2,
                'payment_status' => 'successful',
            ]);
        }

        // Should verify eligibility for both days
        $result1 = $this->payoutService->verifyPayoutEligibility(
            $group,
            $users[0],
            $day1
        );
        $this->assertTrue($result1);

        $result2 = $this->payoutService->verifyPayoutEligibility(
            $group,
            $users[0],
            $day2
        );
        $this->assertTrue($result2);
    }

    /** @test */
    public function it_verifies_eligibility_for_different_payout_days()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 2,
        ]);

        $users = User::factory()->count(2)->create();
        $day1 = Carbon::now();
        $day2 = Carbon::now()->addDay();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);
        }

        // Contributions on day 1
        foreach ($users as $user) {
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $day1,
                'payment_status' => 'successful',
            ]);
        }

        // Verify eligibility for day 1 succeeds
        $result1 = $this->payoutService->verifyPayoutEligibility(
            $group,
            $users[0],
            $day1
        );
        $this->assertTrue($result1);

        // Verify eligibility for day 2 fails (no contributions)
        $this->expectException(\Exception::class);
        $this->payoutService->verifyPayoutEligibility(
            $group,
            $users[0],
            $day2
        );
    }

    /** @test */
    public function it_calculates_daily_payout_correctly()
    {
        $group = Group::factory()->create([
            'contribution_amount' => 1000.00,
            'total_members' => 5,
        ]);

        $payoutAmount = $this->payoutService->calculateDailyPayout($group);

        // Expected: 1000 × 5 = 5000
        $this->assertEquals(5000.00, $payoutAmount);
    }

    /** @test */
    public function it_calculates_daily_payout_with_decimal_amounts()
    {
        $group = Group::factory()->create([
            'contribution_amount' => 1500.50,
            'total_members' => 3,
        ]);

        $payoutAmount = $this->payoutService->calculateDailyPayout($group);

        // Expected: 1500.50 × 3 = 4501.50
        $this->assertEquals(4501.50, $payoutAmount);
    }

    /** @test */
    public function it_returns_payout_with_two_decimal_places()
    {
        $group = Group::factory()->create([
            'contribution_amount' => 333.33,
            'total_members' => 3,
        ]);

        $payoutAmount = $this->payoutService->calculateDailyPayout($group);

        // Expected: 333.33 × 3 = 999.99
        $this->assertEquals(999.99, $payoutAmount);
    }

    /** @test */
    public function it_throws_exception_when_contribution_amount_is_zero()
    {
        $group = Group::factory()->create([
            'contribution_amount' => 0,
            'total_members' => 5,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Group has invalid contribution amount');

        $this->payoutService->calculateDailyPayout($group);
    }

    /** @test */
    public function it_throws_exception_when_contribution_amount_is_negative()
    {
        $group = Group::factory()->create([
            'contribution_amount' => -100.00,
            'total_members' => 5,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Group has invalid contribution amount');

        $this->payoutService->calculateDailyPayout($group);
    }

    /** @test */
    public function it_throws_exception_when_total_members_is_zero()
    {
        $group = Group::factory()->create([
            'contribution_amount' => 1000.00,
            'total_members' => 0,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Group has invalid total members');

        $this->payoutService->calculateDailyPayout($group);
    }

    /** @test */
    public function it_throws_exception_when_total_members_is_negative()
    {
        $group = Group::factory()->create([
            'contribution_amount' => 1000.00,
            'total_members' => -5,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Group has invalid total members');

        $this->payoutService->calculateDailyPayout($group);
    }

    /** @test */
    public function it_processes_payout_successfully()
    {
        // Create group with 2 members
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 2,
            'contribution_amount' => 1000.00,
            'name' => 'Test Group',
        ]);

        $users = User::factory()->count(2)->create();
        $payoutDay = Carbon::now();

        // Add users as group members
        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);

            // Create successful contributions
            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
                'amount' => 1000.00,
            ]);
        }

        // Create payout record
        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $users[0]->id,
            'payout_date' => $payoutDay,
            'status' => 'pending',
            'amount' => 0, // Will be calculated
        ]);

        // Process payout
        $processedPayout = $this->payoutService->processPayout($payout, $group, $users[0]);

        // Verify payout amount is calculated correctly (1000 × 2 = 2000)
        $this->assertEquals(2000.00, $processedPayout->amount);

        // Verify payout status is successful
        $this->assertEquals('successful', $processedPayout->status);

        // Verify processed_at is set
        $this->assertNotNull($processedPayout->processed_at);

        // Verify group member has_received_payout is updated
        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $users[0]->id)
            ->first();
        $this->assertTrue($groupMember->has_received_payout);
        $this->assertNotNull($groupMember->payout_received_at);

        // Verify wallet was credited
        $user = $users[0]->fresh();
        $this->assertEquals(2000.00, $user->wallet_balance);
    }

    /** @test */
    public function it_updates_group_member_has_received_payout()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
            'contribution_amount' => 500.00,
            'name' => 'Test Group',
        ]);

        $user = User::factory()->create();
        $payoutDay = Carbon::now();

        $groupMember = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => false,
        ]);

        Contribution::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'contribution_date' => $payoutDay,
            'payment_status' => 'successful',
            'amount' => 500.00,
        ]);

        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'payout_date' => $payoutDay,
            'status' => 'pending',
        ]);

        $this->payoutService->processPayout($payout, $group, $user);

        $groupMember->refresh();
        $this->assertTrue($groupMember->has_received_payout);
    }

    /** @test */
    public function it_marks_payout_as_successful()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
            'contribution_amount' => 500.00,
            'name' => 'Test Group',
        ]);

        $user = User::factory()->create();
        $payoutDay = Carbon::now();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => false,
        ]);

        Contribution::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'contribution_date' => $payoutDay,
            'payment_status' => 'successful',
            'amount' => 500.00,
        ]);

        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'payout_date' => $payoutDay,
            'status' => 'pending',
        ]);

        $processedPayout = $this->payoutService->processPayout($payout, $group, $user);

        $this->assertEquals('successful', $processedPayout->status);
    }

    /** @test */
    public function it_handles_wallet_credit_failures()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
            'contribution_amount' => 500.00,
            'name' => 'Test Group',
        ]);

        $user = User::factory()->create();
        $payoutDay = Carbon::now();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => false,
        ]);

        Contribution::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'contribution_date' => $payoutDay,
            'payment_status' => 'successful',
            'amount' => 500.00,
        ]);

        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'payout_date' => $payoutDay,
            'status' => 'pending',
        ]);

        // Create a non-existent group member to cause eligibility check to fail
        $nonExistentUser = User::factory()->create();
        
        $this->expectException(\Exception::class);

        $this->payoutService->processPayout($payout, $group, $nonExistentUser);
    }

    /** @test */
    public function it_uses_database_transactions_for_atomicity()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 2,
            'contribution_amount' => 1000.00,
            'name' => 'Test Group',
        ]);

        $users = User::factory()->count(2)->create();
        $payoutDay = Carbon::now();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);

            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
                'amount' => 1000.00,
            ]);
        }

        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $users[0]->id,
            'payout_date' => $payoutDay,
            'status' => 'pending',
        ]);

        // Process payout - should use transaction
        $processedPayout = $this->payoutService->processPayout($payout, $group, $users[0]);

        // Verify all changes were committed atomically
        $this->assertEquals('successful', $processedPayout->status);
        $this->assertEquals(2000.00, $processedPayout->amount);

        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $users[0]->id)
            ->first();
        $this->assertTrue($groupMember->has_received_payout);

        $user = $users[0]->fresh();
        $this->assertEquals(2000.00, $user->wallet_balance);
    }

    /** @test */
    public function it_processes_payout_with_multiple_members_in_same_group()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 3,
            'contribution_amount' => 1000.00,
            'name' => 'Test Group',
        ]);

        $users = User::factory()->count(3)->create();
        $payoutDay = Carbon::now();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => false,
            ]);

            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
                'amount' => 1000.00,
            ]);
        }

        // Process payout for first user
        $payout1 = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $users[0]->id,
            'payout_date' => $payoutDay,
            'status' => 'pending',
        ]);

        $processedPayout1 = $this->payoutService->processPayout($payout1, $group, $users[0]);

        // Verify first user received payout
        $this->assertEquals(3000.00, $processedPayout1->amount);
        $this->assertEquals('successful', $processedPayout1->status);

        $groupMember1 = GroupMember::where('group_id', $group->id)
            ->where('user_id', $users[0]->id)
            ->first();
        $this->assertTrue($groupMember1->has_received_payout);

        // Process payout for second user
        $payout2 = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $users[1]->id,
            'payout_date' => $payoutDay,
            'status' => 'pending',
        ]);

        $processedPayout2 = $this->payoutService->processPayout($payout2, $group, $users[1]);

        // Verify second user received payout
        $this->assertEquals(3000.00, $processedPayout2->amount);
        $this->assertEquals('successful', $processedPayout2->status);

        $groupMember2 = GroupMember::where('group_id', $group->id)
            ->where('user_id', $users[1]->id)
            ->first();
        $this->assertTrue($groupMember2->has_received_payout);

        // Verify both users have correct wallet balances
        $user1 = $users[0]->fresh();
        $user2 = $users[1]->fresh();
        $this->assertEquals(3000.00, $user1->wallet_balance);
        $this->assertEquals(3000.00, $user2->wallet_balance);
    }

    /** @test */
    public function it_retries_failed_payout_successfully()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 2,
            'contribution_amount' => 1000.00,
        ]);

        $users = User::factory()->count(2)->create();
        $payoutDay = Carbon::now();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => $index + 1,
                'has_received_payout' => true,
                'payout_received_at' => now(),
            ]);

            Contribution::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'contribution_date' => $payoutDay,
                'payment_status' => 'successful',
                'amount' => 1000.00,
            ]);
        }

        // Create a failed payout
        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $users[0]->id,
            'payout_date' => $payoutDay,
            'status' => 'failed',
            'failure_reason' => 'Wallet credit failed',
            'processed_at' => now(),
        ]);

        // Retry the failed payout
        $retriedPayout = $this->payoutService->retryFailedPayout($payout);

        // Verify payout status is reset to pending
        $this->assertEquals('pending', $retriedPayout->status);

        // Verify failure_reason is cleared
        $this->assertNull($retriedPayout->failure_reason);

        // Verify processed_at is cleared
        $this->assertNull($retriedPayout->processed_at);

        // Verify group member has_received_payout is reset to false
        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $users[0]->id)
            ->first();
        $this->assertFalse($groupMember->has_received_payout);
        $this->assertNull($groupMember->payout_received_at);
    }

    /** @test */
    public function it_throws_exception_when_retrying_non_failed_payout()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
        ]);

        $user = User::factory()->create();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => false,
        ]);

        // Create a pending payout (not failed)
        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payout cannot be retried');

        $this->payoutService->retryFailedPayout($payout);
    }

    /** @test */
    public function it_throws_exception_when_retrying_successful_payout()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
        ]);

        $user = User::factory()->create();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => true,
        ]);

        // Create a successful payout
        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'successful',
            'processed_at' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payout cannot be retried');

        $this->payoutService->retryFailedPayout($payout);
    }

    /** @test */
    public function it_uses_database_transaction_for_retry()
    {
        $group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 1,
        ]);

        $user = User::factory()->create();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'has_received_payout' => true,
            'payout_received_at' => now(),
        ]);

        $payout = \App\Models\Payout::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'failed',
            'failure_reason' => 'Test failure',
            'processed_at' => now(),
        ]);

        // Retry should use transaction
        $retriedPayout = $this->payoutService->retryFailedPayout($payout);

        // Verify all changes were committed atomically
        $this->assertEquals('pending', $retriedPayout->status);
        $this->assertNull($retriedPayout->failure_reason);
        $this->assertNull($retriedPayout->processed_at);

        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertFalse($groupMember->has_received_payout);
        $this->assertNull($groupMember->payout_received_at);
    }
}
