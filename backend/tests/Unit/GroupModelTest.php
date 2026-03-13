<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\User;
use App\Models\GroupMember;
use App\Models\Contribution;
use App\Models\Payout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_group()
    {
        $user = User::factory()->create();

        $group = Group::create([
            'name' => 'Test Group',
            'description' => 'A test group',
            'group_code' => 'TEST1234',
            'contribution_amount' => 1000.00,
            'total_members' => 10,
            'current_members' => 0,
            'cycle_days' => 10,
            'frequency' => 'daily',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($group);
        $this->assertEquals('Test Group', $group->name);
        $this->assertEquals('pending', $group->status);
        $this->assertEquals(1000.00, $group->contribution_amount);
    }

    /** @test */
    public function it_has_creator_relationship()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $group->creator);
        $this->assertEquals($user->id, $group->creator->id);
    }

    /** @test */
    public function it_has_members_relationship()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();
        
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'position_number' => 1,
            'payout_day' => 1,
            'status' => 'active',
        ]);

        $this->assertCount(1, $group->members);
        $this->assertInstanceOf(GroupMember::class, $group->members->first());
    }

    /** @test */
    public function it_has_contributions_relationship()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();
        
        Contribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'amount' => 1000.00,
            'payment_method' => 'wallet',
            'payment_reference' => 'TEST-REF-123',
            'payment_status' => 'successful',
            'contribution_date' => now(),
        ]);

        $this->assertCount(1, $group->contributions);
        $this->assertInstanceOf(Contribution::class, $group->contributions->first());
    }

    /** @test */
    public function it_has_payouts_relationship()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();
        
        Payout::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'amount' => 10000.00,
            'payout_day' => 1,
            'payout_date' => now(),
            'status' => 'successful',
            'payout_method' => 'wallet',
        ]);

        $this->assertCount(1, $group->payouts);
        $this->assertInstanceOf(Payout::class, $group->payouts->first());
    }

    /** @test */
    public function it_can_filter_by_pending_status()
    {
        Group::factory()->create(['status' => 'pending']);
        Group::factory()->create(['status' => 'active']);
        Group::factory()->create(['status' => 'pending']);

        $pendingGroups = Group::pending()->get();

        $this->assertCount(2, $pendingGroups);
        $this->assertTrue($pendingGroups->every(fn($group) => $group->status === 'pending'));
    }

    /** @test */
    public function it_can_filter_by_active_status()
    {
        Group::factory()->create(['status' => 'pending']);
        Group::factory()->create(['status' => 'active']);
        Group::factory()->create(['status' => 'active']);

        $activeGroups = Group::active()->get();

        $this->assertCount(2, $activeGroups);
        $this->assertTrue($activeGroups->every(fn($group) => $group->status === 'active'));
    }

    /** @test */
    public function it_can_filter_by_completed_status()
    {
        Group::factory()->create(['status' => 'completed']);
        Group::factory()->create(['status' => 'active']);
        Group::factory()->create(['status' => 'completed']);

        $completedGroups = Group::completed()->get();

        $this->assertCount(2, $completedGroups);
        $this->assertTrue($completedGroups->every(fn($group) => $group->status === 'completed'));
    }

    /** @test */
    public function it_can_filter_by_cancelled_status()
    {
        Group::factory()->create(['status' => 'cancelled']);
        Group::factory()->create(['status' => 'active']);

        $cancelledGroups = Group::cancelled()->get();

        $this->assertCount(1, $cancelledGroups);
        $this->assertEquals('cancelled', $cancelledGroups->first()->status);
    }

    /** @test */
    public function it_can_filter_by_any_status()
    {
        Group::factory()->create(['status' => 'pending']);
        Group::factory()->create(['status' => 'active']);
        Group::factory()->create(['status' => 'pending']);

        $pendingGroups = Group::byStatus('pending')->get();

        $this->assertCount(2, $pendingGroups);
    }

    /** @test */
    public function it_checks_if_group_is_pending()
    {
        $group = Group::factory()->create(['status' => 'pending']);

        $this->assertTrue($group->isPending());
        $this->assertFalse($group->isActive());
    }

    /** @test */
    public function it_checks_if_group_is_active()
    {
        $group = Group::factory()->create(['status' => 'active']);

        $this->assertTrue($group->isActive());
        $this->assertFalse($group->isPending());
    }

    /** @test */
    public function it_checks_if_group_is_completed()
    {
        $group = Group::factory()->create(['status' => 'completed']);

        $this->assertTrue($group->isCompleted());
        $this->assertFalse($group->isActive());
    }

    /** @test */
    public function it_checks_if_group_is_cancelled()
    {
        $group = Group::factory()->create(['status' => 'cancelled']);

        $this->assertTrue($group->isCancelled());
        $this->assertFalse($group->isActive());
    }

    /** @test */
    public function it_checks_if_group_is_full()
    {
        $group = Group::factory()->create([
            'total_members' => 10,
            'current_members' => 10,
        ]);

        $this->assertTrue($group->isFull());

        $group->current_members = 5;
        $this->assertFalse($group->isFull());
    }

    /** @test */
    public function it_checks_if_group_can_be_started()
    {
        $group = Group::factory()->create([
            'status' => 'pending',
            'total_members' => 10,
            'current_members' => 10,
        ]);

        $this->assertTrue($group->canBeStarted());

        $group->current_members = 5;
        $this->assertFalse($group->canBeStarted());

        $group->current_members = 10;
        $group->status = 'active';
        $this->assertFalse($group->canBeStarted());
    }
}
