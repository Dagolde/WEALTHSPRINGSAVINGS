<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletFundingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'wallet_balance' => 1000.00,
        ]);
    }

    /** @test */
    public function it_requires_authentication_to_fund_wallet()
    {
        $response = $this->postJson('/api/v1/wallet/fund', [
            'amount' => 5000,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_amount_is_required()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_validates_amount_is_numeric()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 'not-a-number',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_validates_minimum_amount()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 50, // Below minimum of 100
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_validates_maximum_amount()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 15000000, // Above maximum of 10,000,000
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_funds_wallet_with_valid_amount()
    {
        // Mock Paystack API response
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test123',
                    'access_code' => 'test123',
                    'reference' => 'WALLET-20240315-ABC12345',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 5000,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'authorization_url',
                    'access_code',
                    'reference',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('https://checkout.paystack.com', $response->json('data.authorization_url'));
    }

    /** @test */
    public function it_returns_authorization_url_from_paystack()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/xyz789',
                    'access_code' => 'xyz789',
                    'reference' => 'WALLET-20240315-XYZ789',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 10000,
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('https://checkout.paystack.com/xyz789', $data['authorization_url']);
        $this->assertEquals('xyz789', $data['access_code']);
        $this->assertStringStartsWith('WALLET-', $data['reference']);
    }

    /** @test */
    public function it_creates_pending_wallet_transaction()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test123',
                    'access_code' => 'test123',
                    'reference' => 'WALLET-20240315-TEST123',
                ],
            ], 200),
        ]);

        $initialBalance = $this->user->wallet_balance;

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 5000,
            ]);

        $response->assertStatus(200);

        // Check that a pending transaction was created
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 5000.00,
            'balance_before' => $initialBalance,
            'purpose' => 'Wallet funding',
            'status' => 'pending',
        ]);

        // Verify wallet balance hasn't changed yet (pending)
        $this->user->refresh();
        $this->assertEquals($initialBalance, $this->user->wallet_balance);
    }

    /** @test */
    public function it_generates_unique_wallet_reference()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test',
                    'reference' => 'WALLET-20240315-TEST',
                ],
            ], 200),
        ]);

        $response1 = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', ['amount' => 1000]);

        $response2 = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', ['amount' => 2000]);

        $reference1 = $response1->json('data.reference');
        $reference2 = $response2->json('data.reference');

        $this->assertNotEquals($reference1, $reference2);
        $this->assertStringStartsWith('WALLET-', $reference1);
        $this->assertStringStartsWith('WALLET-', $reference2);
    }

    /** @test */
    public function it_handles_paystack_api_error()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => false,
                'message' => 'Invalid API key',
            ], 401),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 5000,
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_sends_correct_data_to_paystack()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test',
                    'reference' => 'WALLET-TEST',
                ],
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 5000,
            ]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $data['email'] === $this->user->email
                && $data['amount'] === 500000 // 5000 * 100 (kobo)
                && isset($data['reference'])
                && str_starts_with($data['reference'], 'WALLET-')
                && isset($data['metadata']['user_id'])
                && $data['metadata']['type'] === 'wallet_funding';
        });
    }

    /** @test */
    public function webhook_processes_successful_wallet_funding()
    {
        // Create a pending wallet transaction
        $reference = 'WALLET-20240315-TEST123';
        $transaction = WalletTransaction::create([
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 5000.00,
            'balance_before' => $this->user->wallet_balance,
            'balance_after' => $this->user->wallet_balance,
            'purpose' => 'Wallet funding',
            'reference' => $reference,
            'metadata' => ['payment_method' => 'paystack'],
            'status' => 'pending',
        ]);

        $initialBalance = $this->user->wallet_balance;

        // Simulate webhook payload
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 500000, // 5000 in kobo
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        // Encode payload to JSON string
        $jsonPayload = json_encode($payload);
        
        // Calculate signature from the JSON string
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.secret_key'));

        // Send raw JSON with signature header using json() instead of postJson()
        $response = $this->json('POST', '/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify wallet was credited
        $this->user->refresh();
        $this->assertEquals($initialBalance + 5000, $this->user->wallet_balance);

        // Verify transaction was updated to successful
        $transaction->refresh();
        $this->assertEquals('successful', $transaction->status);
        $this->assertEquals($initialBalance + 5000, $transaction->balance_after);
    }

    /** @test */
    public function webhook_is_idempotent_for_wallet_funding()
    {
        $reference = 'WALLET-20240315-IDEMPOTENT';
        WalletTransaction::create([
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 3000.00,
            'balance_before' => $this->user->wallet_balance,
            'balance_after' => $this->user->wallet_balance,
            'purpose' => 'Wallet funding',
            'reference' => $reference,
            'metadata' => ['payment_method' => 'paystack'],
            'status' => 'pending',
        ]);

        $initialBalance = $this->user->wallet_balance;

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 300000,
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        // Encode payload to JSON string
        $jsonPayload = json_encode($payload);
        
        // Calculate signature from the JSON string
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.secret_key'));

        // First webhook call
        $response1 = $this->json('POST', '/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);
        $response1->assertStatus(200);

        $balanceAfterFirst = $this->user->fresh()->wallet_balance;

        // Second webhook call (duplicate)
        $response2 = $this->json('POST', '/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);
        $response2->assertStatus(200);

        $balanceAfterSecond = $this->user->fresh()->wallet_balance;

        // Balance should only be credited once
        $this->assertEquals($initialBalance + 3000, $balanceAfterFirst);
        $this->assertEquals($balanceAfterFirst, $balanceAfterSecond);
    }

    /** @test */
    public function webhook_handles_wallet_funding_for_nonexistent_transaction()
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'WALLET-20240315-NONEXISTENT',
                'amount' => 500000,
                'status' => 'success',
                'paid_at' => '2024-03-15T10:30:00.000Z',
            ],
        ];

        // Encode payload to JSON string
        $jsonPayload = json_encode($payload);
        
        // Calculate signature from the JSON string
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.secret_key'));

        $response = $this->json('POST', '/api/v1/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        // Should still return success (webhook processed, just no action taken)
        $response->assertStatus(200);
    }

    /** @test */
    public function it_accepts_amount_at_minimum_boundary()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test',
                    'reference' => 'WALLET-TEST',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 100, // Minimum
            ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_accepts_amount_at_maximum_boundary()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test',
                    'reference' => 'WALLET-TEST',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 10000000, // Maximum
            ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_stores_transaction_metadata()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test',
                    'reference' => 'WALLET-TEST',
                ],
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/fund', [
                'amount' => 5000,
            ]);

        $transaction = WalletTransaction::where('user_id', $this->user->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        $this->assertNotNull($transaction);
        $this->assertIsArray($transaction->metadata);
        $this->assertEquals('paystack', $transaction->metadata['payment_method']);
        $this->assertArrayHasKey('initiated_at', $transaction->metadata);
    }
}
