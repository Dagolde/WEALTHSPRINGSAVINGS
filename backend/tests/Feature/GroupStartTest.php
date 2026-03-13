<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupStartTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->creator = User::factory()->create([
            'status' => 'active',
            'kyc_status' => 'verified',
        ]);
        
        $this->member = User::factory()->create([
            'status' => 'active',
            'kyc_status' => 'verified',
        ]);
    }

    /**
     * Helper method to create group members with unique temporary position numbers
     */
    private function createGroupMembers(Group $group, array $users): void
    {
        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => -($index + 1), // Use different negative numbers
                'payout_day' => 0,
            ]);
        }
    }

    /** @test */
    public function it_starts_a_full_group_successfully()
    {
        // Create a group with 3 members
        $group = Group::factory()->create([
            'total_members' => 3,
            'current_members' => 3,
            'status' => 'pending',
            'created_by' => $this->creator->id,
            'cycle_days' => 3,
            'frequency' => 'daily',
        ]);

        // Add members with different temporary position numbers
        $users = [$this->creator, $this->member, User::factory()->create()];
        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'position_number' => -($index + 1), // Use different negative numbers
                'payout_day' => 0,
            ]);
        }

        $response = $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Group started successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'status',
                    'start_date',
                    'end_date',
                ],
            ]);

        // Verify group status is updated to 'active'
        $group->refresh();
        $this->assertEquals('active', $group->status);
        $this->assertNotNull($group->start_date);
        $this->assertNotNull($group->end_date);
    }

    /** @test */
    public function it_assigns_unique_position_numbers_to_all_members()
    {
        $group = Group::factory()->create([
            'total_members' => 5,
            'current_members' => 5,
            'status' => 'pending',
            'created_by' => $this->creator->id, // Set creator
            'cycle_days' => 5,
        ]);

        // Add 5 members with different temporary position numbers
        $users = User::factory()->count(5)->create();
        $this->createGroupMembers($group, $users->all());

        $response = $this->actingAs($this->creator, 'sanctum') // Use creator
            ->postJson("/api/v1/groups/{$group->id}/start");

        $response->assertStatus(200);

        // Verify all members have unique positions from 1 to N
        $members = GroupMember::where('group_id', $group->id)->get();
        $positions = $members->pluck('position_number')->sort()->values()->toArray();
        
        $this->assertEquals([1, 2, 3, 4, 5], $positions);
        
        // Verify no duplicate positions
        $this->assertEquals(5, $members->pluck('position_number')->unique()->count());
    }

    /** @test */
    public function it_calculates_payout_day_correctly_for_each_member()
    {
        $group = Group::factory()->create([
            'total_members' => 3,
            'current_members' => 3,
            'status' => 'pending',
            'created_by' => $this->creator->id, // Set creator
            'cycle_days' => 3,
            'frequency' => 'daily',
        ]);

        $users = User::factory()->count(3)->create();
        $this->createGroupMembers($group, $users->all());

        $this->actingAs($this->creator, 'sanctum') // Use creator
            ->postJson("/api/v1/groups/{$group->id}/start");

        // Verify payout_day equals position_number for each member
        $members = GroupMember::where('group_id', $group->id)->get();
        
        foreach ($members as $member) {
            $this->assertEquals($member->position_number, $member->payout_day);
            $this->assertGreaterThanOrEqual(1, $member->payout_day);
            $this->assertLessThanOrEqual(3, $member->payout_day);
        }
    }

    /** @test */
    public function it_sets_start_date_to_today()
    {
        $group = Group::factory()->create([
            'total_members' => 2,
            'current_members' => 2,
            'status' => 'pending',
            'created_by' => $this->creator->id,
            'cycle_days' => 2,
        ]);

        $users = User::factory()->count(2)->create();
        $this->createGroupMembers($group, $users->all());

        $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $group->refresh();
        $this->assertEquals(now()->startOfDay()->toDateString(), $group->start_date->toDateString());
    }

    /** @test */
    public function it_calculates_end_date_correctly_for_daily_frequency()
    {
        $group = Group::factory()->create([
            'total_members' => 2,
            'current_members' => 2,
            'status' => 'pending',
            'created_by' => $this->creator->id,
            'cycle_days' => 10,
            'frequency' => 'daily',
        ]);

        $users = User::factory()->count(2)->create();
        $this->createGroupMembers($group, $users->all());

        $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $group->refresh();
        
        // end_date = start_date + (cycle_days - 1) days
        $expectedEndDate = now()->startOfDay()->addDays(9);
        $this->assertEquals($expectedEndDate->toDateString(), $group->end_date->toDateString());
    }

    /** @test */
    public function it_calculates_end_date_correctly_for_weekly_frequency()
    {
        $group = Group::factory()->create([
            'total_members' => 2,
            'current_members' => 2,
            'status' => 'pending',
            'created_by' => $this->creator->id,
            'cycle_days' => 4,
            'frequency' => 'weekly',
        ]);

        $users = User::factory()->count(2)->create();
        $this->createGroupMembers($group, $users->all());

        $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $group->refresh();
        
        // end_date = start_date + (cycle_days - 1) weeks
        $expectedEndDate = now()->startOfDay()->addWeeks(3);
        $this->assertEquals($expectedEndDate->toDateString(), $group->end_date->toDateString());
    }

    /** @test */
    public function it_rejects_start_if_user_is_not_the_creator()
    {
        $group = Group::factory()->create([
            'total_members' => 2,
            'current_members' => 2,
            'status' => 'pending',
            'created_by' => $this->creator->id,
        ]);

        $users = User::factory()->count(2)->create();
        $this->createGroupMembers($group, $users->all());

        // Try to start as non-creator
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only the group creator can start the group',
            ]);

        // Verify group status is still pending
        $group->refresh();
        $this->assertEquals('pending', $group->status);
    }

    /** @test */
    public function it_rejects_start_if_group_is_not_pending()
    {
        $group = Group::factory()->create([
            'total_members' => 2,
            'current_members' => 2,
            'status' => 'active', // Already active
            'created_by' => $this->creator->id,
        ]);

        GroupMember::factory()->count(2)->create([
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot start group. Group is not in pending status.',
            ]);
    }

    /** @test */
    public function it_rejects_start_if_group_is_not_full()
    {
        $group = Group::factory()->create([
            'total_members' => 5,
            'current_members' => 3, // Not full
            'status' => 'pending',
            'created_by' => $this->creator->id,
        ]);

        $users = User::factory()->count(3)->create();
        $this->createGroupMembers($group, $users->all());

        $response = $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
            ])
            ->assertJsonPath('message', function ($message) {
                return str_contains($message, 'Cannot start group. Group is not full');
            });

        // Verify group status is still pending
        $group->refresh();
        $this->assertEquals('pending', $group->status);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $group = Group::factory()->create([
            'total_members' => 2,
            'current_members' => 2,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/groups/{$group->id}/start");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_404_if_group_does_not_exist()
    {
        $response = $this->actingAs($this->creator, 'sanctum')
            ->postJson('/api/v1/groups/99999/start');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Group not found',
            ]);
    }

    /** @test */
    public function it_uses_database_transaction_for_atomicity()
    {
        $group = Group::factory()->create([
            'total_members' => 3,
            'current_members' => 3,
            'status' => 'pending',
            'created_by' => $this->creator->id, // Set creator
            'cycle_days' => 3,
        ]);

        $users = User::factory()->count(3)->create();
        $this->createGroupMembers($group, $users->all());

        $this->actingAs($this->creator, 'sanctum') // Use creator
            ->postJson("/api/v1/groups/{$group->id}/start");

        // If transaction works correctly, all members should have positions assigned
        // and group should be active
        $group->refresh();
        $this->assertEquals('active', $group->status);
        
        $members = GroupMember::where('group_id', $group->id)->get();
        foreach ($members as $member) {
            $this->assertGreaterThan(0, $member->position_number);
            $this->assertGreaterThan(0, $member->payout_day);
        }
    }

    /** @test */
    public function it_assigns_positions_randomly()
    {
        // Create multiple groups and verify positions are not always the same
        $positionSets = [];
        
        for ($i = 0; $i < 10; $i++) { // Increase iterations for better randomness detection
            $creator = User::factory()->create();
            $group = Group::factory()->create([
                'total_members' => 5,
                'current_members' => 5,
                'status' => 'pending',
                'created_by' => $creator->id,
                'cycle_days' => 5,
            ]);

            $users = User::factory()->count(5)->create();
            $this->createGroupMembers($group, $users->all());

            $this->actingAs($creator, 'sanctum')
                ->postJson("/api/v1/groups/{$group->id}/start");

            // Get the order of positions by user_id
            $members = GroupMember::where('group_id', $group->id)
                ->orderBy('user_id')
                ->get();
            
            $positionSets[] = $members->pluck('position_number')->toArray();
        }

        // Verify that not all position sets are identical (randomness check)
        $uniqueSets = array_unique($positionSets, SORT_REGULAR);
        $this->assertGreaterThan(1, count($uniqueSets), 'Positions should be assigned randomly');
    }

    /** @test */
    public function it_handles_concurrent_start_attempts_safely()
    {
        $group = Group::factory()->create([
            'total_members' => 2,
            'current_members' => 2,
            'status' => 'pending',
            'created_by' => $this->creator->id,
            'cycle_days' => 2,
        ]);

        $users = User::factory()->count(2)->create();
        $this->createGroupMembers($group, $users->all());

        // First start should succeed
        $response1 = $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $response1->assertStatus(200);

        // Second start attempt should fail (group is no longer pending)
        $response2 = $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/groups/{$group->id}/start");

        $response2->assertStatus(422);
    }
}
