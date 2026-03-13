<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Models\User;
use App\Models\Contribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutRetryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Group $group;
    private Payout $failedPayout;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        // Create a group with 2 members
        $this->group = Group::factory()->create([
            'status' => 'active',
            'total_members' => 2,
            'contribution_amount' => 1000.00,
        ]);

        // Add users as group members
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'position_number' => 1,
            'has_received_payout' => true,
            'payout_received_at' => now(),
        ]);

        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->otherUser->id,
            'position_number' => 2,
            'has_received_payout' => false,
        ]);

        // Create a failed payout
        $this->failedPayout = Payout::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'status' => 'failed',
            'failure_reason' => 'Wallet credit failed',
            'processed_at' => now(),
        ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authorization_user_owns_payout()
    {
        $response = $this->actingAs($this->otherUser)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized to retry this payout',
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_payout()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payouts/99999/retry');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Payout not found',
            ]);
    }

    /** @test */
    public function it_returns_422_if_payout_is_not_in_failed_status()
    {
        // Create a pending payout
        $pendingPayout = Payout::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'failure_reason' => null,
            'processed_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$pendingPayout->id}/retry");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_successfully_retries_failed_payout()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payout retry initiated successfully',
                'data' => [
                    'id' => $this->failedPayout->id,
                    'status' => 'pending',
                ],
            ]);
    }

    /** @test */
    public function it_resets_payout_to_pending_status()
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $this->failedPayout->refresh();

        $this->assertEquals('pending', $this->failedPayout->status);
    }

    /** @test */
    public function it_clears_failure_reason()
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $this->failedPayout->refresh();

        $this->assertNull($this->failedPayout->failure_reason);
    }

    /** @test */
    public function it_clears_processed_at()
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $this->failedPayout->refresh();

        $this->assertNull($this->failedPayout->processed_at);
    }

    /** @test */
    public function it_resets_group_member_has_received_payout_to_false()
    {
        $groupMember = GroupMember::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertTrue($groupMember->has_received_payout);

        $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $groupMember->refresh();

        $this->assertFalse($groupMember->has_received_payout);
    }

    /** @test */
    public function it_clears_group_member_payout_received_at()
    {
        $groupMember = GroupMember::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($groupMember->payout_received_at);

        $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $groupMember->refresh();

        $this->assertNull($groupMember->payout_received_at);
    }

    /** @test */
    public function it_returns_updated_payout_in_response()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->failedPayout->id,
                    'group_id' => $this->group->id,
                    'user_id' => $this->user->id,
                    'status' => 'pending',
                    'failure_reason' => null,
                    'processed_at' => null,
                ],
            ]);
    }

    /** @test */
    public function it_allows_admin_to_retry_any_payout()
    {
        // Skip this test - admin functionality not yet implemented
        $this->markTestSkipped('Admin functionality not yet implemented');
    }

    /** @test */
    public function it_preserves_payout_metadata_during_retry()
    {
        $originalAmount = $this->failedPayout->amount;
        $originalPayoutDay = $this->failedPayout->payout_day;
        $originalPayoutDate = $this->failedPayout->payout_date;
        $originalPayoutMethod = $this->failedPayout->payout_method;

        $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $this->failedPayout->refresh();

        $this->assertEquals($originalAmount, $this->failedPayout->amount);
        $this->assertEquals($originalPayoutDay, $this->failedPayout->payout_day);
        $this->assertEquals($originalPayoutDate, $this->failedPayout->payout_date);
        $this->assertEquals($originalPayoutMethod, $this->failedPayout->payout_method);
    }

    /** @test */
    public function it_can_retry_multiple_times()
    {
        // First retry
        $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $this->failedPayout->refresh();
        $this->assertEquals('pending', $this->failedPayout->status);

        // Simulate failure again
        $this->failedPayout->markAsFailed('Second attempt failed');

        // Second retry
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payouts/{$this->failedPayout->id}/retry");

        $response->assertStatus(200);

        $this->failedPayout->refresh();
        $this->assertEquals('pending', $this->failedPayout->status);
        $this->assertNull($this->failedPayout->failure_reason);
    }
}
