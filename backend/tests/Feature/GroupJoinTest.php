<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupJoinTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->creator = User::factory()->create([
            'email' => 'creator@example.com',
            'status' => 'active',
        ]);
        
        $this->user = User::factory()->create([
            'email' => 'member@example.com',
            'status' => 'active',
        ]);
    }

    public function test_user_can_join_pending_group_with_available_capacity(): void
    {
        // Create a group with capacity for more members
        $group = Group::factory()->create([
            'created_by' => $this->creator->id,
            'total_members' => 5,
            'current_members' => 1,
            'status' => 'pending',
        ]);

        // Add creator as first member (manually to avoid factory position assignment)
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
            'position_number' => -1,
            'payout_day' => 0,
            'has_received_payout' => false,
            'joined_at' => now(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/groups/{$group->id}/join");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully joined group',
                'data' => [
                    'id' => $group->id,
                    'current_members' => 2,
                    'total_members' => 5,
                    'status' => 'pending',
                ],
            ]);

        // Verify user was added as a member
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // Verify current_members was incremented
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'current_members' => 2,
        ]);
    }

    public function test_cannot_join_group_that_does_not_exist(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/groups/99999/join');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Group not found',
            ]);
    }

    public function test_cannot_join_group_that_is_not_pending(): void
    {
        $group = Group::factory()->create([
            'created_by' => $this->creator->id,
            'total_members' => 5,
            'current_members' => 5,
            'status' => 'active', // Not pending
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/groups/{$group->id}/join");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot join group. Group is not in pending status.',
            ]);

        // Verify user was not added as a member
        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_join_full_group(): void
    {
        $group = Group::factory()->create([
            'created_by' => $this->creator->id,
            'total_members' => 2,
            'current_members' => 2, // Group is full
            'status' => 'pending',
        ]);

        // Add creator and another member
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
        ]);
        
        $otherUser = User::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/groups/{$group->id}/join");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot join group. Group is already full.',
            ]);

        // Verify user was not added as a member
        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->user->id,
        ]);

        // Verify current_members was not incremented
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'current_members' => 2,
        ]);
    }

    public function test_cannot_join_group_if_already_a_member(): void
    {
        $group = Group::factory()->create([
            'created_by' => $this->creator->id,
            'total_members' => 5,
            'current_members' => 2,
            'status' => 'pending',
        ]);

        // Add creator as first member
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
        ]);

        // Add user as a member
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/groups/{$group->id}/join");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You are already a member of this group.',
            ]);

        // Verify current_members was not incremented
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'current_members' => 2,
        ]);
    }

    public function test_joining_group_increments_current_members_atomically(): void
    {
        $group = Group::factory()->create([
            'created_by' => $this->creator->id,
            'total_members' => 10,
            'current_members' => 1,
            'status' => 'pending',
        ]);

        // Add creator as first member
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
        ]);

        $initialCount = $group->current_members;

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/groups/{$group->id}/join");

        $response->assertStatus(200);

        // Verify current_members was incremented by exactly 1
        $group->refresh();
        $this->assertEquals($initialCount + 1, $group->current_members);
    }

    public function test_multiple_users_can_join_group_until_full(): void
    {
        $group = Group::factory()->create([
            'created_by' => $this->creator->id,
            'total_members' => 3,
            'current_members' => 1,
            'status' => 'pending',
        ]);

        // Add creator as first member
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
        ]);

        $user2 = User::factory()->create(['email' => 'user2@example.com']);
        $user3 = User::factory()->create(['email' => 'user3@example.com']);

        // User 2 joins
        $response = $this->actingAs($user2)
            ->postJson("/api/v1/groups/{$group->id}/join");
        $response->assertStatus(200);

        // User 3 joins (group should now be full)
        $response = $this->actingAs($user3)
            ->postJson("/api/v1/groups/{$group->id}/join");
        $response->assertStatus(200);

        // Verify group is now full
        $group->refresh();
        $this->assertEquals(3, $group->current_members);
        $this->assertTrue($group->isFull());

        // Another user tries to join (should fail)
        $user4 = User::factory()->create(['email' => 'user4@example.com']);
        $response = $this->actingAs($user4)
            ->postJson("/api/v1/groups/{$group->id}/join");
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot join group. Group is already full.',
            ]);
    }

    public function test_unauthenticated_user_cannot_join_group(): void
    {
        $group = Group::factory()->create([
            'created_by' => $this->creator->id,
            'total_members' => 5,
            'current_members' => 1,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/groups/{$group->id}/join");

        $response->assertStatus(401);
    }

    public function test_joining_group_creates_member_with_correct_initial_values(): void
    {
        $group = Group::factory()->create([
            'created_by' => $this->creator->id,
            'total_members' => 5,
            'current_members' => 1,
            'status' => 'pending',
        ]);

        // Add creator as first member
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/groups/{$group->id}/join");

        $response->assertStatus(200);

        // Verify member record has correct initial values
        $member = GroupMember::where('group_id', $group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertLessThan(0, $member->position_number); // Temporary negative value, will be assigned when group starts
        $this->assertEquals(0, $member->payout_day); // Will be calculated when group starts
        $this->assertFalse($member->has_received_payout);
        $this->assertEquals('active', $member->status);
        $this->assertNotNull($member->joined_at);
    }
}
