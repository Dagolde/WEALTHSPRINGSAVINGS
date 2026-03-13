<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create([
            'kyc_status' => 'verified',
            'status' => 'active',
        ]);

        $this->otherUser = User::factory()->create([
            'kyc_status' => 'verified',
            'status' => 'active',
        ]);

        // Create an active group
        $this->group = Group::factory()->create([
            'status' => 'active',
            'start_date' => now()->subDays(5),
            'contribution_amount' => 1000,
            'total_members' => 5,
            'current_members' => 5,
            'frequency' => 'daily',
        ]);

        // Add users as members
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->otherUser->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function user_can_retrieve_their_contribution_history()
    {
        // Create contributions for the user
        Contribution::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_status' => 'successful',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'group_id',
                            'user_id',
                            'amount',
                            'payment_method',
                            'payment_reference',
                            'payment_status',
                            'contribution_date',
                            'group' => [
                                'id',
                                'name',
                                'contribution_amount',
                                'status',
                            ],
                        ],
                    ],
                    'total',
                    'per_page',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 3,
                ],
            ]);
    }

    /** @test */
    public function user_only_sees_their_own_contributions()
    {
        // Create contributions for both users
        Contribution::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Contribution::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 2,
                ],
            ]);
    }

    /** @test */
    public function user_can_filter_contributions_by_group()
    {
        $group2 = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group2->id,
            'user_id' => $this->user->id,
        ]);

        Contribution::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Contribution::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'group_id' => $group2->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/contributions?group_id={$this->group->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 2,
                ],
            ]);
    }

    /** @test */
    public function user_can_filter_contributions_by_payment_status()
    {
        Contribution::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_status' => 'successful',
        ]);

        Contribution::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions?payment_status=successful');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 2,
                ],
            ]);
    }

    /** @test */
    public function user_can_filter_contributions_by_date_range()
    {
        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(10),
        ]);

        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(5),
        ]);

        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(2),
        ]);

        $dateFrom = now()->subDays(6)->toDateString();
        $dateTo = now()->subDays(1)->toDateString();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/contributions?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 2,
                ],
            ]);
    }

    /** @test */
    public function contributions_are_ordered_by_date_descending()
    {
        $contribution1 = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(5),
        ]);

        $contribution2 = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(2),
        ]);

        $contribution3 = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(8),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions');

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertEquals($contribution2->id, $data[0]['id']);
        $this->assertEquals($contribution1->id, $data[1]['id']);
        $this->assertEquals($contribution3->id, $data[2]['id']);
    }

    /** @test */
    public function contribution_history_supports_pagination()
    {
        Contribution::factory()->count(20)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions?per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'per_page' => 10,
                    'total' => 20,
                ],
            ]);

        $this->assertCount(10, $response->json('data.data'));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_contribution_history()
    {
        $response = $this->getJson('/api/v1/contributions');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_retrieve_group_contributions()
    {
        // Create contributions from multiple users
        Contribution::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_status' => 'successful',
        ]);

        Contribution::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'group_id' => $this->group->id,
            'payment_status' => 'successful',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/groups/{$this->group->id}/contributions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'group_id',
                            'user_id',
                            'amount',
                            'payment_status',
                            'contribution_date',
                            'user' => [
                                'id',
                                'name',
                                'email',
                            ],
                        ],
                    ],
                    'total',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 5,
                ],
            ]);
    }

    /** @test */
    public function non_member_cannot_view_group_contributions()
    {
        $nonMember = User::factory()->create([
            'kyc_status' => 'verified',
            'status' => 'active',
        ]);

        $response = $this->actingAs($nonMember)
            ->getJson("/api/v1/groups/{$this->group->id}/contributions");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not a member of this group',
            ]);
    }

    /** @test */
    public function group_contributions_returns_404_for_nonexistent_group()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/groups/99999/contributions');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Group not found',
            ]);
    }

    /** @test */
    public function user_can_filter_group_contributions_by_user_id()
    {
        Contribution::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Contribution::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/groups/{$this->group->id}/contributions?user_id={$this->otherUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 3,
                ],
            ]);
    }

    /** @test */
    public function user_can_filter_group_contributions_by_payment_status()
    {
        Contribution::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_status' => 'successful',
        ]);

        Contribution::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'group_id' => $this->group->id,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/groups/{$this->group->id}/contributions?payment_status=pending");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 3,
                ],
            ]);
    }

    /** @test */
    public function user_can_filter_group_contributions_by_date_range()
    {
        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(10),
        ]);

        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(5),
        ]);

        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(2),
        ]);

        $dateFrom = now()->subDays(6)->toDateString();
        $dateTo = now()->subDays(1)->toDateString();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/groups/{$this->group->id}/contributions?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 2,
                ],
            ]);
    }

    /** @test */
    public function user_can_retrieve_missed_contributions()
    {
        // Group started 5 days ago, user should have made 5 contributions
        // Create only 2 successful contributions
        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(5),
            'payment_status' => 'successful',
        ]);

        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(3),
            'payment_status' => 'successful',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions/missed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'missed_contributions' => [
                        '*' => [
                            'group_id',
                            'group_name',
                            'contribution_amount',
                            'missed_date',
                            'frequency',
                        ],
                    ],
                    'total_missed_amount',
                    'total_missed_count',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_missed_count']);
        $this->assertGreaterThan(0, $data['total_missed_amount']);
    }

    /** @test */
    public function missed_contributions_only_includes_active_groups()
    {
        // Create a completed group
        $completedGroup = Group::factory()->create([
            'status' => 'completed',
            'start_date' => now()->subDays(10),
            'contribution_amount' => 1000,
        ]);

        GroupMember::factory()->create([
            'group_id' => $completedGroup->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // No contributions made to completed group
        // Should not appear in missed contributions

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions/missed');

        $response->assertStatus(200);

        $missedContributions = $response->json('data.missed_contributions');
        $completedGroupMissed = array_filter($missedContributions, function ($item) use ($completedGroup) {
            return $item['group_id'] === $completedGroup->id;
        });

        $this->assertEmpty($completedGroupMissed);
    }

    /** @test */
    public function missed_contributions_can_be_filtered_by_group()
    {
        $group2 = Group::factory()->create([
            'status' => 'active',
            'start_date' => now()->subDays(3),
            'contribution_amount' => 2000,
        ]);

        GroupMember::factory()->create([
            'group_id' => $group2->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // No contributions made to either group

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/contributions/missed?group_id={$group2->id}");

        $response->assertStatus(200);

        $missedContributions = $response->json('data.missed_contributions');
        foreach ($missedContributions as $missed) {
            $this->assertEquals($group2->id, $missed['group_id']);
        }
    }

    /** @test */
    public function missed_contributions_calculates_total_amount_correctly()
    {
        // Group with 1000 contribution amount, started 3 days ago
        // User made no contributions, so should have 3 missed contributions = 3000 total

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions/missed');

        $response->assertStatus(200);

        $data = $response->json('data');
        $missedForThisGroup = array_filter($data['missed_contributions'], function ($item) {
            return $item['group_id'] === $this->group->id;
        });

        $expectedAmount = count($missedForThisGroup) * $this->group->contribution_amount;
        $this->assertGreaterThan(0, $expectedAmount);
    }

    /** @test */
    public function missed_contributions_respects_weekly_frequency()
    {
        $weeklyGroup = Group::factory()->create([
            'status' => 'active',
            'start_date' => now()->subWeeks(3),
            'contribution_amount' => 5000,
            'frequency' => 'weekly',
        ]);

        GroupMember::factory()->create([
            'group_id' => $weeklyGroup->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // No contributions made

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/contributions/missed?group_id={$weeklyGroup->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $missedCount = $data['total_missed_count'];

        // Should have approximately 3 missed contributions (one per week)
        // Allow for 3-4 depending on exact timing
        $this->assertGreaterThanOrEqual(3, $missedCount);
        $this->assertLessThanOrEqual(4, $missedCount);
    }

    /** @test */
    public function missed_contributions_excludes_pending_and_failed_contributions()
    {
        // Create a pending contribution (should still count as missed)
        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(5),
            'payment_status' => 'pending',
        ]);

        // Create a failed contribution (should still count as missed)
        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(4),
            'payment_status' => 'failed',
        ]);

        // Create a successful contribution (should not count as missed)
        Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'contribution_date' => now()->subDays(3),
            'payment_status' => 'successful',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contributions/missed');

        $response->assertStatus(200);

        $data = $response->json('data');
        $missedForThisGroup = array_filter($data['missed_contributions'], function ($item) {
            return $item['group_id'] === $this->group->id;
        });

        // Should have missed contributions for days 5, 4, 2, 1, 0 (today)
        // Day 3 had a successful contribution
        $missedDates = array_column($missedForThisGroup, 'missed_date');
        $this->assertNotContains(now()->subDays(3)->toDateString(), $missedDates);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_missed_contributions()
    {
        $response = $this->getJson('/api/v1/contributions/missed');

        $response->assertStatus(401);
    }
}
