<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        // Create a group with 3 members
        $this->group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 3,
            'current_members' => 3,
            'contribution_amount' => 1000.00,
            'start_date' => now()->subDays(2),
        ]);

        // Add users as group members
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'position_number' => 1,
            'payout_day' => 1,
            'has_received_payout' => true,
            'payout_received_at' => now()->subDays(2),
        ]);

        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->otherUser->id,
            'position_number' => 2,
            'payout_day' => 2,
            'has_received_payout' => false,
        ]);

        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => User::factory()->create()->id,
            'position_number' => 3,
            'payout_day' => 3,
            'has_received_payout' => false,
        ]);
    }

    // ========== GET /api/v1/payouts/schedule/{groupId} Tests ==========

    /** @test */
    public function it_requires_authentication_for_schedule()
    {
        $response = $this->getJson("/api/v1/payouts/schedule/{$this->group->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_group()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payouts/schedule/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Group not found',
            ]);
    }

    /** @test */
    public function it_requires_user_to_be_group_member_for_schedule()
    {
        $nonMember = User::factory()->create();

        $response = $this->actingAs($nonMember)
            ->getJson("/api/v1/payouts/schedule/{$this->group->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not a member of this group',
            ]);
    }

    /** @test */
    public function it_returns_payout_schedule_for_group()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/schedule/{$this->group->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payout schedule retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'payout_day',
                        'payout_date',
                        'user_id',
                        'user_name',
                        'position_number',
                        'amount',
                        'status',
                        'has_received_payout',
                    ],
                ],
            ]);
    }

    /** @test */
    public function schedule_returns_all_members_in_position_order()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/schedule/{$this->group->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);

        // Verify order by position
        $this->assertEquals(1, $data[0]['position_number']);
        $this->assertEquals(2, $data[1]['position_number']);
        $this->assertEquals(3, $data[2]['position_number']);
    }

    /** @test */
    public function schedule_calculates_correct_payout_dates()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/schedule/{$this->group->id}");

        $response->assertStatus(200);

        $data = $response->json('data');

        // First member: start_date + 0 days
        $expectedDate1 = $this->group->start_date->format('Y-m-d');
        $this->assertEquals($expectedDate1, $data[0]['payout_date']);

        // Second member: start_date + 1 day
        $expectedDate2 = $this->group->start_date->addDay()->format('Y-m-d');
        $this->assertEquals($expectedDate2, $data[1]['payout_date']);
    }

    /** @test */
    public function schedule_shows_correct_payout_amount()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/schedule/{$this->group->id}");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Amount should be contribution_amount * total_members
        $expectedAmount = $this->group->contribution_amount * $this->group->total_members;

        foreach ($data as $item) {
            $this->assertEquals($expectedAmount, $item['amount']);
        }
    }

    /** @test */
    public function schedule_reflects_payout_status_when_payout_exists()
    {
        // Create a successful payout for the first member
        Payout::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'payout_day' => 1,
            'status' => 'successful',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/schedule/{$this->group->id}");

        $response->assertStatus(200);

        $data = $response->json('data');

        // First member should show successful status
        $this->assertEquals('successful', $data[0]['status']);
        $this->assertTrue($data[0]['has_received_payout']);

        // Other members should show pending
        $this->assertEquals('pending', $data[1]['status']);
        $this->assertFalse($data[1]['has_received_payout']);
    }

    // ========== GET /api/v1/payouts/history Tests ==========

    /** @test */
    public function it_requires_authentication_for_history()
    {
        $response = $this->getJson('/api/v1/payouts/history');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_empty_history_for_user_with_no_payouts()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payouts/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payout history retrieved successfully',
                'data' => [
                    'data' => [],
                    'total' => 0,
                ],
            ]);
    }

    /** @test */
    public function it_returns_user_payout_history()
    {
        // Create payouts for the user
        Payout::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payouts/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payout history retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'group_id',
                            'group_name',
                            'amount',
                            'payout_day',
                            'payout_date',
                            'status',
                            'payout_method',
                            'processed_at',
                            'created_at',
                        ],
                    ],
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.total'));
    }

    /** @test */
    public function history_only_returns_authenticated_user_payouts()
    {
        // Create payouts for both users
        Payout::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Payout::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payouts/history');

        $response->assertStatus(200);

        // Should only return 2 payouts for this user
        $this->assertEquals(2, $response->json('data.total'));
    }

    /** @test */
    public function history_supports_pagination()
    {
        // Create 20 payouts
        Payout::factory()->count(20)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payouts/history?per_page=5&page=2');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_page' => 2,
                    'per_page' => 5,
                    'total' => 20,
                    'last_page' => 4,
                ],
            ]);

        $this->assertCount(5, $response->json('data.data'));
    }

    /** @test */
    public function history_can_filter_by_status()
    {
        // Create payouts with different statuses
        Payout::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'status' => 'successful',
        ]);

        Payout::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payouts/history?status=successful');

        $response->assertStatus(200);

        $this->assertEquals(2, $response->json('data.total'));

        $data = $response->json('data.data');
        foreach ($data as $payout) {
            $this->assertEquals('successful', $payout['status']);
        }
    }

    /** @test */
    public function history_can_filter_by_group_id()
    {
        $anotherGroup = Group::factory()->create();

        // Create payouts for different groups
        Payout::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Payout::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'group_id' => $anotherGroup->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/history?group_id={$this->group->id}");

        $response->assertStatus(200);

        $this->assertEquals(2, $response->json('data.total'));

        $data = $response->json('data.data');
        foreach ($data as $payout) {
            $this->assertEquals($this->group->id, $payout['group_id']);
        }
    }

    /** @test */
    public function history_returns_payouts_in_descending_order_by_created_at()
    {
        // Create payouts at different times
        $payout1 = Payout::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'created_at' => now()->subDays(3),
        ]);

        $payout2 = Payout::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'created_at' => now()->subDays(1),
        ]);

        $payout3 = Payout::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payouts/history');

        $response->assertStatus(200);

        $data = $response->json('data.data');

        // Most recent should be first
        $this->assertEquals($payout3->id, $data[0]['id']);
        $this->assertEquals($payout2->id, $data[1]['id']);
        $this->assertEquals($payout1->id, $data[2]['id']);
    }

    // ========== GET /api/v1/payouts/{id} Tests ==========

    /** @test */
    public function it_requires_authentication_for_show()
    {
        $payout = Payout::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->getJson("/api/v1/payouts/{$payout->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_payout()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payouts/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Payout not found',
            ]);
    }

    /** @test */
    public function it_requires_user_to_own_payout()
    {
        $payout = Payout::factory()->create([
            'user_id' => $this->otherUser->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/{$payout->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized to view this payout',
            ]);
    }

    /** @test */
    public function it_returns_payout_details()
    {
        $payout = Payout::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'amount' => 3000.00,
            'payout_day' => 1,
            'status' => 'successful',
            'payout_method' => 'wallet',
            'payout_reference' => 'PAY-TEST-123',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/{$payout->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payout details retrieved successfully',
                'data' => [
                    'id' => $payout->id,
                    'group_id' => $this->group->id,
                    'group_name' => $this->group->name,
                    'user_id' => $this->user->id,
                    'amount' => 3000.00,
                    'payout_day' => 1,
                    'status' => 'successful',
                    'payout_method' => 'wallet',
                    'payout_reference' => 'PAY-TEST-123',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'group_id',
                    'group_name',
                    'user_id',
                    'amount',
                    'payout_day',
                    'payout_date',
                    'status',
                    'payout_method',
                    'payout_reference',
                    'failure_reason',
                    'processed_at',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    /** @test */
    public function show_includes_failure_reason_for_failed_payouts()
    {
        $payout = Payout::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'status' => 'failed',
            'failure_reason' => 'Insufficient wallet balance',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/{$payout->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'failed',
                    'failure_reason' => 'Insufficient wallet balance',
                ],
            ]);
    }

    /** @test */
    public function show_includes_processed_at_for_processed_payouts()
    {
        $processedAt = now()->subHours(2);

        $payout = Payout::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'status' => 'successful',
            'processed_at' => $processedAt,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/{$payout->id}");

        $response->assertStatus(200);

        $this->assertNotNull($response->json('data.processed_at'));
    }

    /** @test */
    public function show_returns_null_for_unprocessed_payout_fields()
    {
        $payout = Payout::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'status' => 'pending',
            'payout_reference' => null,
            'failure_reason' => null,
            'processed_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payouts/{$payout->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payout_reference' => null,
                    'failure_reason' => null,
                    'processed_at' => null,
                ],
            ]);
    }
}
