<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secretKey = 'test_secret_key_12345';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.paystack.secret_key', $this->secretKey);
    }

    /**
     * Generate valid Paystack webhook signature.
     */
    private function generateSignature(array $payload): string
    {
        return hash_hmac('sha512', json_encode($payload), $this->secretKey);
    }

    /**
     * Test webhook with valid signature is accepted.
     */
    public function test_webhook_with_valid_signature_is_accepted(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $contribution = Contribution::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-ABC12345',
            'amount' => 1000.00,
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $contribution->payment_reference,
                'amount' => 100000, // Amount in kobo
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        // Verify contribution was updated
        $contribution->refresh();
        $this->assertEquals('successful', $contribution->payment_status);
        $this->assertNotNull($contribution->paid_at);

        // Verify wallet was credited
        $user->refresh();
        $this->assertEquals(1000.00, $user->wallet_balance);

        // Verify wallet transaction was created
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 1000.00,
            'reference' => $contribution->payment_reference,
            'status' => 'successful',
        ]);
    }

    /**
     * Test webhook with invalid signature is rejected.
     */
    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'CONT-20240315-ABC12345',
                'amount' => 100000,
                'status' => 'success',
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => 'invalid_signature',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid webhook signature',
            ]);
    }

    /**
     * Test webhook without signature header is rejected.
     */
    public function test_webhook_without_signature_is_rejected(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'CONT-20240315-ABC12345',
                'amount' => 100000,
                'status' => 'success',
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid webhook signature',
            ]);
    }

    /**
     * Test idempotent webhook processing - same webhook processed twice.
     */
    public function test_webhook_idempotency_same_webhook_processed_twice(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $contribution = Contribution::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-IDEMPOTENT',
            'amount' => 1000.00,
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $contribution->payment_reference,
                'amount' => 100000,
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        $signature = $this->generateSignature($payload);

        // First webhook call
        $response1 = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response1->assertStatus(200);

        // Verify initial state
        $user->refresh();
        $this->assertEquals(1000.00, $user->wallet_balance);

        // Second webhook call (duplicate)
        $response2 = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook already processed',
            ]);

        // Verify wallet balance hasn't changed (idempotency)
        $user->refresh();
        $this->assertEquals(1000.00, $user->wallet_balance);

        // Verify only one wallet transaction exists
        $transactionCount = WalletTransaction::where('user_id', $user->id)
            ->where('reference', $contribution->payment_reference)
            ->count();
        $this->assertEquals(1, $transactionCount);
    }

    /**
     * Test webhook for wallet payment method does not credit wallet.
     */
    public function test_webhook_for_wallet_payment_does_not_credit_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance' => 500.00]);
        $group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $contribution = Contribution::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_method' => 'wallet', // Wallet payment
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-WALLET',
            'amount' => 1000.00,
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $contribution->payment_reference,
                'amount' => 100000,
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify contribution was updated
        $contribution->refresh();
        $this->assertEquals('successful', $contribution->payment_status);

        // Verify wallet was NOT credited (should remain 500)
        $user->refresh();
        $this->assertEquals(500.00, $user->wallet_balance);

        // Verify no wallet transaction was created for this webhook
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'reference' => $contribution->payment_reference,
        ]);
    }

    /**
     * Test webhook for bank_transfer payment credits wallet.
     */
    public function test_webhook_for_bank_transfer_payment_credits_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $contribution = Contribution::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-BANK',
            'amount' => 2000.00,
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $contribution->payment_reference,
                'amount' => 200000, // Amount in kobo
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify wallet was credited
        $user->refresh();
        $this->assertEquals(2000.00, $user->wallet_balance);

        // Verify wallet transaction was created
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 2000.00,
            'reference' => $contribution->payment_reference,
            'status' => 'successful',
        ]);
    }

    /**
     * Test webhook for failed payment updates contribution status.
     */
    public function test_webhook_for_failed_payment_updates_contribution_status(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $contribution = Contribution::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-FAILED',
            'amount' => 1000.00,
        ]);

        $payload = [
            'event' => 'charge.failed',
            'data' => [
                'reference' => $contribution->payment_reference,
                'amount' => 100000,
                'status' => 'failed',
                'gateway_response' => 'Insufficient funds',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify contribution was marked as failed
        $contribution->refresh();
        $this->assertEquals('failed', $contribution->payment_status);
        $this->assertNull($contribution->paid_at);

        // Verify wallet was NOT credited
        $user->refresh();
        $this->assertEquals(0, $user->wallet_balance);

        // Verify no wallet transaction was created
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $user->id,
            'reference' => $contribution->payment_reference,
        ]);
    }

    /**
     * Test webhook with non-existent payment reference is handled gracefully.
     */
    public function test_webhook_with_non_existent_reference_is_handled_gracefully(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'CONT-20240315-NONEXISTENT',
                'amount' => 100000,
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        // Should still return 200 to acknowledge receipt
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);
    }

    /**
     * Test webhook with missing reference is rejected.
     */
    public function test_webhook_with_missing_reference_is_rejected(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'amount' => 100000,
                'status' => 'success',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Missing payment reference',
            ]);
    }

    /**
     * Test webhook updates already successful contribution idempotently.
     */
    public function test_webhook_for_already_successful_contribution_is_idempotent(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1000.00]);
        $group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // Create contribution that's already successful
        $contribution = Contribution::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_method' => 'card',
            'payment_status' => 'successful', // Already successful
            'payment_reference' => 'CONT-20240315-ALREADY',
            'amount' => 1000.00,
            'paid_at' => now()->subHour(),
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $contribution->payment_reference,
                'amount' => 100000,
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify wallet balance hasn't changed
        $user->refresh();
        $this->assertEquals(1000.00, $user->wallet_balance);

        // Verify no additional wallet transaction was created
        $transactionCount = WalletTransaction::where('user_id', $user->id)
            ->where('reference', $contribution->payment_reference)
            ->count();
        $this->assertEquals(0, $transactionCount);
    }

    /**
     * Test wallet transaction records correct balance before and after.
     */
    public function test_wallet_transaction_records_correct_balances(): void
    {
        $initialBalance = 500.00;
        $user = User::factory()->create(['wallet_balance' => $initialBalance]);
        $group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $contributionAmount = 1000.00;
        $contribution = Contribution::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-BALANCE',
            'amount' => $contributionAmount,
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $contribution->payment_reference,
                'amount' => 100000,
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify wallet transaction has correct balances
        $transaction = WalletTransaction::where('user_id', $user->id)
            ->where('reference', $contribution->payment_reference)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($initialBalance, $transaction->balance_before);
        $this->assertEquals($initialBalance + $contributionAmount, $transaction->balance_after);

        // Verify user's current balance matches transaction's balance_after
        $user->refresh();
        $this->assertEquals($transaction->balance_after, $user->wallet_balance);
    }

    /**
     * Test cache-based idempotency check works correctly.
     */
    public function test_cache_based_idempotency_check(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $group = Group::factory()->create(['status' => 'active']);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $contribution = Contribution::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'payment_reference' => 'CONT-20240315-CACHE',
            'amount' => 1000.00,
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $contribution->payment_reference,
                'amount' => 100000,
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        $signature = $this->generateSignature($payload);

        // First call - should process
        $response1 = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response1->assertStatus(200);

        // Verify cache key was set
        $cacheKey = "webhook_processed_{$contribution->payment_reference}";
        $this->assertTrue(Cache::has($cacheKey));

        // Second call - should be caught by cache
        $response2 = $this->postJson('/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook already processed',
            ]);
    }
}
