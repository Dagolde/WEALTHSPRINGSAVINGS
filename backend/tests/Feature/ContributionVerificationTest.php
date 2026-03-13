<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContributionVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $secretKey = 'test_secret_key_12345';
    private User $user;
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.paystack.secret_key', $this->secretKey);

        // Create test user and group
        $this->user = User::factory()->create(['wallet_balance' => 0]);
        $this->group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test successful payment verification.
     */
    public function test_successful_payment_verification(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-SUCCESS',
            'amount' => 1000.00,
        ]);

        // Mock Paystack API response
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 100000,
                    'status' => 'success',
                    'paid_at' => '2024-03-15T10:30:00.000Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Contribution verified successfully',
            ]);

        // Verify contribution was updated
        $contribution->refresh();
        $this->assertEquals('successful', $contribution->payment_status);
        $this->assertNotNull($contribution->paid_at);

        // Verify wallet was credited (card payment)
        $this->user->refresh();
        $this->assertEquals(1000.00, $this->user->wallet_balance);

        // Verify wallet transaction was created
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 1000.00,
            'reference' => $contribution->payment_reference,
            'status' => 'successful',
        ]);
    }

    /**
     * Test failed payment verification.
     */
    public function test_failed_payment_verification(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-FAILED',
            'amount' => 1000.00,
        ]);

        // Mock Paystack API response for failed payment
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 100000,
                    'status' => 'failed',
                    'gateway_response' => 'Insufficient funds',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
            ]);

        // Verify contribution was marked as failed
        $contribution->refresh();
        $this->assertEquals('failed', $contribution->payment_status);
        $this->assertNull($contribution->paid_at);

        // Verify wallet was NOT credited
        $this->user->refresh();
        $this->assertEquals(0, $this->user->wallet_balance);

        // Verify no wallet transaction was created
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $this->user->id,
            'reference' => $contribution->payment_reference,
        ]);
    }

    /**
     * Test pending payment verification.
     */
    public function test_pending_payment_verification(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-PENDING',
            'amount' => 1000.00,
        ]);

        // Mock Paystack API response for pending payment
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 100000,
                    'status' => 'pending',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Payment is still pending. Please try again later.',
            ]);

        // Verify contribution status remains pending
        $contribution->refresh();
        $this->assertEquals('pending', $contribution->payment_status);
        $this->assertNull($contribution->paid_at);

        // Verify wallet was NOT credited
        $this->user->refresh();
        $this->assertEquals(0, $this->user->wallet_balance);
    }

    /**
     * Test wallet crediting for card payment.
     */
    public function test_wallet_crediting_for_card_payment(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-CARD',
            'amount' => 2500.00,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 250000,
                    'status' => 'success',
                    'paid_at' => '2024-03-15T10:30:00.000Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(200);

        // Verify wallet was credited
        $this->user->refresh();
        $this->assertEquals(2500.00, $this->user->wallet_balance);

        // Verify wallet transaction
        $transaction = WalletTransaction::where('user_id', $this->user->id)
            ->where('reference', $contribution->payment_reference)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('credit', $transaction->type);
        $this->assertEquals(2500.00, $transaction->amount);
        $this->assertEquals(0, $transaction->balance_before);
        $this->assertEquals(2500.00, $transaction->balance_after);
    }

    /**
     * Test wallet crediting for bank_transfer payment.
     */
    public function test_wallet_crediting_for_bank_transfer_payment(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-BANK',
            'amount' => 3000.00,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 300000,
                    'status' => 'success',
                    'paid_at' => '2024-03-15T10:30:00.000Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(200);

        // Verify wallet was credited
        $this->user->refresh();
        $this->assertEquals(3000.00, $this->user->wallet_balance);
    }

    /**
     * Test no wallet credit for wallet payment method.
     */
    public function test_no_wallet_credit_for_wallet_payment(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'wallet',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-WALLET',
            'amount' => 1000.00,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 100000,
                    'status' => 'success',
                    'paid_at' => '2024-03-15T10:30:00.000Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(200);

        // Verify contribution was updated
        $contribution->refresh();
        $this->assertEquals('successful', $contribution->payment_status);

        // Verify wallet was NOT credited (wallet payment)
        $this->user->refresh();
        $this->assertEquals(0, $this->user->wallet_balance);

        // Verify no wallet transaction was created
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $this->user->id,
            'type' => 'credit',
            'reference' => $contribution->payment_reference,
        ]);
    }

    /**
     * Test idempotency - already verified contribution.
     */
    public function test_already_verified_contribution_returns_idempotent_response(): void
    {
        // Create already successful contribution with wallet already credited
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'successful',
            'payment_reference' => 'CONT-20240315-ALREADY',
            'amount' => 1000.00,
            'paid_at' => now()->subHour(),
        ]);

        // Simulate wallet already credited
        $this->user->update(['wallet_balance' => 1000.00]);
        WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 1000.00,
            'balance_before' => 0,
            'balance_after' => 1000.00,
            'reference' => $contribution->payment_reference,
            'purpose' => 'Test',
            'status' => 'successful',
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 100000,
                    'status' => 'success',
                    'paid_at' => '2024-03-15T10:30:00.000Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Contribution already verified as successful',
            ]);

        // Verify wallet balance hasn't changed
        $this->user->refresh();
        $this->assertEquals(1000.00, $this->user->wallet_balance);

        // Verify no additional wallet transaction was created
        $transactionCount = WalletTransaction::where('user_id', $this->user->id)
            ->where('reference', $contribution->payment_reference)
            ->count();
        $this->assertEquals(1, $transactionCount);
    }

    /**
     * Test non-existent payment reference.
     */
    public function test_non_existent_payment_reference(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => 'CONT-20240315-NONEXISTENT',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Contribution not found with the provided payment reference',
            ]);
    }

    /**
     * Test unauthorized access - contribution belongs to another user.
     */
    public function test_unauthorized_access_contribution_belongs_to_another_user(): void
    {
        $otherUser = User::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $contribution = Contribution::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-OTHER',
            'amount' => 1000.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to verify this contribution',
            ]);
    }

    /**
     * Test Paystack API error handling.
     */
    public function test_paystack_api_error_handling(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-ERROR',
            'amount' => 1000.00,
        ]);

        // Mock Paystack API error
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => false,
                'message' => 'Transaction not found',
            ], 404),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'message' => 'Unable to verify payment with gateway. Please try again later.',
            ]);

        // Verify contribution status hasn't changed
        $contribution->refresh();
        $this->assertEquals('pending', $contribution->payment_status);
    }

    /**
     * Test Paystack API network error.
     */
    public function test_paystack_api_network_error(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-NETWORK',
            'amount' => 1000.00,
        ]);

        // Mock network error
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'message' => 'Unable to verify payment with gateway. Please try again later.',
            ]);
    }

    /**
     * Test missing payment_reference validation.
     */
    public function test_missing_payment_reference_validation(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_reference']);
    }

    /**
     * Test unauthenticated access.
     */
    public function test_unauthenticated_access(): void
    {
        $response = $this->postJson('/api/v1/contributions/verify', [
            'payment_reference' => 'CONT-20240315-TEST',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test wallet not credited twice for same contribution.
     */
    public function test_wallet_not_credited_twice_for_same_contribution(): void
    {
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-DOUBLE',
            'amount' => 1000.00,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 100000,
                    'status' => 'success',
                    'paid_at' => '2024-03-15T10:30:00.000Z',
                ],
            ], 200),
        ]);

        // First verification
        $response1 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response1->assertStatus(200);

        // Verify wallet was credited
        $this->user->refresh();
        $this->assertEquals(1000.00, $this->user->wallet_balance);

        // Second verification (should be idempotent)
        $response2 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response2->assertStatus(200);

        // Verify wallet balance hasn't changed
        $this->user->refresh();
        $this->assertEquals(1000.00, $this->user->wallet_balance);

        // Verify only one wallet transaction exists
        $transactionCount = WalletTransaction::where('user_id', $this->user->id)
            ->where('reference', $contribution->payment_reference)
            ->count();
        $this->assertEquals(1, $transactionCount);
    }

    /**
     * Test wallet transaction records correct balances.
     */
    public function test_wallet_transaction_records_correct_balances(): void
    {
        $initialBalance = 500.00;
        $this->user->update(['wallet_balance' => $initialBalance]);

        $contributionAmount = 1500.00;
        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-BALANCE',
            'amount' => $contributionAmount,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'reference' => $contribution->payment_reference,
                    'amount' => 150000,
                    'status' => 'success',
                    'paid_at' => '2024-03-15T10:30:00.000Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(200);

        // Verify wallet transaction has correct balances
        $transaction = WalletTransaction::where('user_id', $this->user->id)
            ->where('reference', $contribution->payment_reference)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($initialBalance, $transaction->balance_before);
        $this->assertEquals($initialBalance + $contributionAmount, $transaction->balance_after);

        // Verify user's current balance matches transaction's balance_after
        $this->user->refresh();
        $this->assertEquals($transaction->balance_after, $this->user->wallet_balance);
        $this->assertEquals(2000.00, $this->user->wallet_balance);
    }

    /**
     * Test Paystack secret key not configured.
     */
    public function test_paystack_secret_key_not_configured(): void
    {
        Config::set('services.paystack.secret_key', null);

        $contribution = Contribution::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-NOKEY',
            'amount' => 1000.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contributions/verify', [
                'payment_reference' => $contribution->payment_reference,
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Paystack secret key not configured',
            ]);
    }
}

