<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupListingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->otherUser = User::factory()->create([
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_lists_user_groups_with_pagination()
    {
        // Create multiple groups where user is a member
        $groups = Group::factory()->count(20)->create([
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);

        foreach ($groups as $group) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $this->user->id,
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/groups');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'group_code',
                            'contribution_amount',
                            'total_members',
                            'current_members',
                            'status',
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ]
            ]);

        $this->assertEquals(15, count($response->json('data.data')));
        $this->assertEquals(20, $response->json('data.total'));
    }

    /** @test */
    public function it_filters_groups_by_status()
    {
        // Create groups with different statuses
        $pendingGroup = Group::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);
        GroupMember::factory()->create([
            'group_id' => $pendingGroup->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $activeGroup = Group::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'start_date' => now(),
        ]);
        GroupMember::factory()->create([
            'group_id' => $activeGroup->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // Filter by active status
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/groups?status=active');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data.data')));
        $this->assertEquals('active', $response->json('data.data.0.status'));
    }

    /** @test */
    public function it_only_shows_groups_where_user_is_member()
    {
        // Create group where user is a member
        $userGroup = Group::factory()->create(['created_by' => $this->user->id]);
        GroupMember::factory()->create([
            'group_id' => $userGroup->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // Create group where user is NOT a member
        $otherGroup = Group::factory()->create(['created_by' => $this->otherUser->id]);
        GroupMember::factory()->create([
            'group_id' => $otherGroup->id,
            'user_id' => $this->otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/groups');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data.data')));
        $this->assertEquals($userGroup->id, $response->json('data.data.0.id'));
    }

    /** @test */
    public function it_requires_authentication_to_list_groups()
    {
        $response = $this->getJson('/api/v1/groups');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_shows_group_details_with_creator_info()
    {
        $group = Group::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'group_code',
                    'contribution_amount',
                    'total_members',
                    'current_members',
                    'status',
                    'creator' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'member_count',
                ]
            ]);

        $this->assertEquals($this->user->id, $response->json('data.creator.id'));
        $this->assertEquals(1, $response->json('data.member_count'));
    }

    /** @test */
    public function it_returns_404_for_non_existent_group()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/groups/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Group not found',
            ]);
    }

    /** @test */
    public function it_returns_403_if_user_is_not_member_of_group()
    {
        $group = Group::factory()->create(['created_by' => $this->otherUser->id]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/groups/{$group->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not a member of this group',
            ]);
    }

    /** @test */
    public function it_lists_group_members_ordered_by_position()
    {
        $group = Group::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'total_members' => 3,
            'current_members' => 3,
        ]);

        // Create members with different positions
        $member1 = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'position_number' => 2,
            'payout_day' => 2,
            'status' => 'active',
        ]);

        $member2 = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->otherUser->id,
            'position_number' => 1,
            'payout_day' => 1,
            'status' => 'active',
        ]);

        $user3 = User::factory()->create(['status' => 'active']);
        $member3 = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user3->id,
            'position_number' => 3,
            'payout_day' => 3,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/groups/{$group->id}/members");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'position_number',
                        'payout_day',
                        'has_received_payout',
                        'joined_at',
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ]
                ]
            ]);

        $members = $response->json('data');
        $this->assertEquals(3, count($members));
        
        // Verify ordering by position_number
        $this->assertEquals(1, $members[0]['position_number']);
        $this->assertEquals(2, $members[1]['position_number']);
        $this->assertEquals(3, $members[2]['position_number']);
    }

    /** @test */
    public function it_returns_403_when_non_member_tries_to_view_members()
    {
        $group = Group::factory()->create(['created_by' => $this->otherUser->id]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/groups/{$group->id}/members");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not a member of this group',
            ]);
    }

    /** @test */
    public function it_calculates_payout_schedule_for_daily_frequency()
    {
        $startDate = now()->startOfDay();
        $group = Group::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'total_members' => 3,
            'current_members' => 3,
            'frequency' => 'daily',
            'start_date' => $startDate,
        ]);

        // Create members with positions
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'position_number' => 1,
            'payout_day' => 1,
            'status' => 'active',
        ]);

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->otherUser->id,
            'position_number' => 2,
            'payout_day' => 2,
            'status' => 'active',
        ]);

        $user3 = User::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user3->id,
            'position_number' => 3,
            'payout_day' => 3,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/groups/{$group->id}/schedule");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'position_number',
                        'payout_day',
                        'payout_date',
                        'has_received_payout',
                        'member' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ]
                ]
            ]);

        $schedule = $response->json('data');
        $this->assertEquals(3, count($schedule));

        // Verify payout dates are calculated correctly (daily)
        $this->assertEquals($startDate->format('Y-m-d'), $schedule[0]['payout_date']);
        $this->assertEquals($startDate->copy()->addDay()->format('Y-m-d'), $schedule[1]['payout_date']);
        $this->assertEquals($startDate->copy()->addDays(2)->format('Y-m-d'), $schedule[2]['payout_date']);
    }

    /** @test */
    public function it_calculates_payout_schedule_for_weekly_frequency()
    {
        $startDate = now()->startOfDay();
        $group = Group::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'total_members' => 2,
            'current_members' => 2,
            'frequency' => 'weekly',
            'start_date' => $startDate,
        ]);

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'position_number' => 1,
            'payout_day' => 1,
            'status' => 'active',
        ]);

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->otherUser->id,
            'position_number' => 2,
            'payout_day' => 2,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/groups/{$group->id}/schedule");

        $response->assertStatus(200);

        $schedule = $response->json('data');
        
        // Verify payout dates are calculated correctly (weekly)
        $this->assertEquals($startDate->format('Y-m-d'), $schedule[0]['payout_date']);
        $this->assertEquals($startDate->copy()->addWeek()->format('Y-m-d'), $schedule[1]['payout_date']);
    }

    /** @test */
    public function it_returns_422_when_group_has_not_started()
    {
        $group = Group::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'pending',
            'start_date' => null,
        ]);

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/groups/{$group->id}/schedule");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Group has not started yet. Payout schedule not available.',
            ]);
    }

    /** @test */
    public function it_returns_403_when_non_member_tries_to_view_schedule()
    {
        $group = Group::factory()->create([
            'created_by' => $this->otherUser->id,
            'status' => 'active',
            'start_date' => now(),
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/groups/{$group->id}/schedule");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not a member of this group',
            ]);
    }
}
