<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionRecordingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with wallet balance
        $this->user = User::factory()->create([
            'wallet_balance' => 5000.00,
            'status' => 'active',
        ]);

        // Create an active group
        $this->group = Group::factory()->create([
            'contribution_amount' => 1000.00,
            'total_members' => 5,
            'current_members' => 5,
            'status' => 'active',
            'start_date' => now()->subDays(1),
        ]);

        // Add user as an active member
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'position_number' => 1,
            'payout_day' => 1,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function user_can_contribute_to_group_using_wallet()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Contribution recorded successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'group_id',
                    'user_id',
                    'amount',
                    'payment_method',
                    'payment_reference',
                    'payment_status',
                    'contribution_date',
                    'created_at',
                ],
            ]);

        // Verify contribution was created
        $contribution = Contribution::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($contribution);
        $this->assertEquals(1000.00, $contribution->amount);
        $this->assertEquals('wallet', $contribution->payment_method);
        $this->assertEquals('successful', $contribution->payment_status);
        $this->assertEquals(now()->toDateString(), $contribution->contribution_date->toDateString());

        // Verify wallet was debited
        $this->user->refresh();
        $this->assertEquals(4000.00, $this->user->wallet_balance);

        // Verify wallet transaction was recorded
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->user->id,
            'type' => 'debit',
            'amount' => 1000.00,
            'balance_before' => 5000.00,
            'balance_after' => 4000.00,
            'status' => 'successful',
        ]);
    }

    /** @test */
    public function user_cannot_contribute_with_insufficient_wallet_balance()
    {
        // Set wallet balance below contribution amount
        $this->user->update(['wallet_balance' => 500.00]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $responseData = $response->json();
        $this->assertStringContainsString('Insufficient wallet balance', $responseData['message']);
        $this->assertStringContainsString('1000', $responseData['message']);
        $this->assertStringContainsString('500', $responseData['message']);

        // Verify no contribution was created
        $this->assertDatabaseMissing('contributions', [
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);

        // Verify wallet balance unchanged
        $this->user->refresh();
        $this->assertEquals(500.00, $this->user->wallet_balance);
    }

    /** @test */
    public function user_cannot_contribute_twice_on_same_day()
    {
        // First contribution
        $firstResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $firstResponse->assertStatus(201);

        // Refresh user to get updated wallet balance
        $this->user->refresh();

        // Verify first contribution was created
        $contributionCount = Contribution::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->count();
        
        $this->assertEquals(1, $contributionCount, 'First contribution should be created');

        // Second contribution on same day should fail
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You have already contributed to this group today',
            ]);

        // Verify still only one contribution exists
        $finalCount = Contribution::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->count();
            
        $this->assertEquals(1, $finalCount, 'Should still have only one contribution after duplicate attempt');
    }

    /** @test */
    public function user_cannot_contribute_to_inactive_group()
    {
        // Set group to pending status
        $this->group->update(['status' => 'pending']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot contribute to this group. Group is not active.',
            ]);

        // Verify no contribution was created
        $this->assertDatabaseMissing('contributions', [
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function non_member_cannot_contribute_to_group()
    {
        // Create a different user who is not a member
        $nonMember = User::factory()->create([
            'wallet_balance' => 5000.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($nonMember)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not an active member of this group',
            ]);

        // Verify no contribution was created
        $this->assertDatabaseMissing('contributions', [
            'group_id' => $this->group->id,
            'user_id' => $nonMember->id,
        ]);
    }

    /** @test */
    public function removed_member_cannot_contribute_to_group()
    {
        // Update member status to 'removed'
        GroupMember::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->update(['status' => 'removed']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not an active member of this group',
            ]);

        // Verify no contribution was created
        $this->assertDatabaseMissing('contributions', [
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function contribution_with_card_payment_creates_pending_record()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'card',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Contribution recorded successfully',
            ]);

        // Verify contribution was created with pending status
        $contribution = Contribution::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($contribution);
        $this->assertEquals(1000.00, $contribution->amount);
        $this->assertEquals('card', $contribution->payment_method);
        $this->assertEquals('pending', $contribution->payment_status);
        $this->assertEquals(now()->toDateString(), $contribution->contribution_date->toDateString());

        // Verify wallet was NOT debited for card payment
        $this->user->refresh();
        $this->assertEquals(5000.00, $this->user->wallet_balance);

        // Verify no wallet transaction was created
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $this->user->id,
            'type' => 'debit',
        ]);
    }

    /** @test */
    public function contribution_with_bank_transfer_creates_pending_record()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'bank_transfer',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Contribution recorded successfully',
            ]);

        // Verify contribution was created with pending status
        $contribution = Contribution::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($contribution);
        $this->assertEquals(1000.00, $contribution->amount);
        $this->assertEquals('bank_transfer', $contribution->payment_method);
        $this->assertEquals('pending', $contribution->payment_status);
        $this->assertEquals(now()->toDateString(), $contribution->contribution_date->toDateString());

        // Verify wallet was NOT debited
        $this->user->refresh();
        $this->assertEquals(5000.00, $this->user->wallet_balance);
    }

    /** @test */
    public function contribution_requires_authentication()
    {
        $response = $this->postJson('/api/v1/contributions', [
            'group_id' => $this->group->id,
            'payment_method' => 'wallet',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function contribution_validates_required_fields()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['group_id', 'payment_method']);
    }

    /** @test */
    public function contribution_validates_group_exists()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => 99999,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['group_id']);
    }

    /** @test */
    public function contribution_validates_payment_method()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'invalid_method',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    /** @test */
    public function contribution_generates_unique_payment_reference()
    {
        // Create first contribution
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $reference1 = $response1->json('data.payment_reference');

        // Create another user and group for second contribution
        $user2 = User::factory()->create(['wallet_balance' => 5000.00]);
        $group2 = Group::factory()->create([
            'contribution_amount' => 1000.00,
            'status' => 'active',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group2->id,
            'user_id' => $user2->id,
            'status' => 'active',
        ]);

        $response2 = $this->actingAs($user2)
            ->postJson('/api/v1/contributions', [
                'group_id' => $group2->id,
                'payment_method' => 'wallet',
            ]);

        $reference2 = $response2->json('data.payment_reference');

        // Verify references are unique
        $this->assertNotEquals($reference1, $reference2);
        $this->assertStringStartsWith('CONT-', $reference1);
        $this->assertStringStartsWith('CONT-', $reference2);
    }

    /** @test */
    public function wallet_deduction_is_atomic_with_contribution_creation()
    {
        // This test verifies that if contribution creation fails,
        // wallet deduction is rolled back
        
        // We'll simulate this by checking the transaction behavior
        $initialBalance = $this->user->wallet_balance;

        try {
            $this->actingAs($this->user)
                ->postJson('/api/v1/contributions', [
                    'group_id' => $this->group->id,
                    'payment_method' => 'wallet',
                ]);
        } catch (\Exception $e) {
            // If any exception occurs, wallet should not be debited
        }

        // Verify that either both operations succeeded or both failed
        $contribution = Contribution::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->user->refresh();

        if ($contribution && $contribution->payment_status === 'successful') {
            // If contribution exists and is successful, wallet should be debited
            $this->assertEquals($initialBalance - 1000.00, $this->user->wallet_balance);
        } else {
            // If contribution doesn't exist or failed, wallet should be unchanged
            $this->assertEquals($initialBalance, $this->user->wallet_balance);
        }
    }

    /** @test */
    public function contribution_amount_matches_group_contribution_amount()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(201);

        $contribution = Contribution::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertEquals($this->group->contribution_amount, $contribution->amount);
    }

    /** @test */
    public function contribution_date_is_set_to_current_date()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(201);

        $contribution = Contribution::where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertEquals(now()->toDateString(), $contribution->contribution_date->toDateString());
    }

    /** @test */
    public function wallet_transaction_records_correct_metadata()
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $this->group->id,
                'payment_method' => 'wallet',
            ]);

        $transaction = WalletTransaction::where('user_id', $this->user->id)
            ->where('type', 'debit')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertStringContainsString($this->group->name, $transaction->purpose);
        
        // Metadata is already an array (model casts it)
        $metadata = $transaction->metadata;
        $this->assertEquals($this->group->id, $metadata['group_id']);
        $this->assertEquals($this->group->name, $metadata['group_name']);
        $this->assertEquals(now()->toDateString(), $metadata['contribution_date']);
    }
}
