<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['wallet_balance' => 50000.00]);
    }

    protected User $user;

    // ============ GET /api/v1/wallet/balance Tests ============

    public function test_requires_authentication_to_get_balance(): void
    {
        $response = $this->getJson('/api/v1/wallet/balance');

        $response->assertUnauthorized();
    }

    public function test_returns_current_wallet_balance(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/balance');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'balance',
                    'currency',
                ],
            ])
            ->assertJsonPath('data.balance', 50000.00)
            ->assertJsonPath('data.currency', 'NGN');
    }

    public function test_balance_is_cached_for_five_minutes(): void
    {
        // First request - should hit database
        $response1 = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/balance');

        $response1->assertOk()
            ->assertJsonPath('data.balance', 50000.00);

        // Update balance directly in database
        $this->user->update(['wallet_balance' => 75000.00]);

        // Second request within 5 minutes - should return cached value
        $response2 = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/balance');

        $response2->assertOk()
            ->assertJsonPath('data.balance', 50000.00); // Still cached value

        // Clear cache and verify new value is returned
        \Illuminate\Support\Facades\Cache::forget("wallet_balance_{$this->user->id}");

        $response3 = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/balance');

        $response3->assertOk()
            ->assertJsonPath('data.balance', 75000.00); // New value after cache clear
    }

    public function test_balance_endpoint_returns_zero_for_new_user(): void
    {
        $newUser = User::factory()->create(['wallet_balance' => 0.00]);

        $response = $this->actingAs($newUser)
            ->getJson('/api/v1/wallet/balance');

        $response->assertOk()
            ->assertJsonPath('data.balance', 0.00);
    }

    public function test_balance_endpoint_returns_decimal_precision(): void
    {
        $this->user->update(['wallet_balance' => 12345.67]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/balance');

        $response->assertOk()
            ->assertJsonPath('data.balance', 12345.67);
    }

    // ============ GET /api/v1/wallet/transactions Tests ============

    public function test_requires_authentication_to_get_transactions(): void
    {
        $response = $this->getJson('/api/v1/wallet/transactions');

        $response->assertUnauthorized();
    }

    public function test_returns_paginated_transaction_history(): void
    {
        // Create 20 transactions
        WalletTransaction::factory(20)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transactions' => [
                        '*' => [
                            'id',
                            'type',
                            'amount',
                            'balance_before',
                            'balance_after',
                            'purpose',
                            'reference',
                            'status',
                            'created_at',
                        ],
                    ],
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                        'last_page',
                    ],
                ],
            ])
            ->assertJsonPath('data.pagination.total', 20)
            ->assertJsonPath('data.pagination.per_page', 15)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2);

        // Verify only 15 transactions on first page
        $this->assertCount(15, $response->json('data.transactions'));
    }

    public function test_transactions_are_ordered_by_most_recent_first(): void
    {
        $transaction1 = WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subHours(2),
        ]);

        $transaction2 = WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subHour(),
        ]);

        $transaction3 = WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk();

        $transactions = $response->json('data.transactions');
        $this->assertEquals($transaction3->id, $transactions[0]['id']);
        $this->assertEquals($transaction2->id, $transactions[1]['id']);
        $this->assertEquals($transaction1->id, $transactions[2]['id']);
    }

    public function test_respects_per_page_parameter(): void
    {
        WalletTransaction::factory(30)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions?per_page=10');

        $response->assertOk()
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.last_page', 3);

        $this->assertCount(10, $response->json('data.transactions'));
    }

    public function test_respects_page_parameter(): void
    {
        WalletTransaction::factory(30)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions?per_page=10&page=2');

        $response->assertOk()
            ->assertJsonPath('data.pagination.current_page', 2);

        $this->assertCount(10, $response->json('data.transactions'));
    }

    public function test_per_page_parameter_is_capped_at_100(): void
    {
        WalletTransaction::factory(150)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions?per_page=200');

        $response->assertOk()
            ->assertJsonPath('data.pagination.per_page', 100);

        $this->assertCount(100, $response->json('data.transactions'));
    }

    public function test_per_page_parameter_minimum_is_1(): void
    {
        WalletTransaction::factory(5)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions?per_page=0');

        $response->assertOk()
            ->assertJsonPath('data.pagination.per_page', 1);

        $this->assertCount(1, $response->json('data.transactions'));
    }

    public function test_returns_empty_transactions_for_user_with_no_transactions(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonPath('data.transactions', []);
    }

    public function test_only_returns_transactions_for_authenticated_user(): void
    {
        $otherUser = User::factory()->create();

        WalletTransaction::factory(5)->create(['user_id' => $this->user->id]);
        WalletTransaction::factory(5)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 5);
    }

    public function test_transaction_includes_all_required_fields(): void
    {
        $transaction = WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 5000.00,
            'balance_before' => 10000.00,
            'balance_after' => 15000.00,
            'purpose' => 'Wallet funding',
            'reference' => 'TXN-20240315-ABC12345',
            'status' => 'successful',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk();

        $transactions = $response->json('data.transactions');
        $this->assertCount(1, $transactions);

        $txn = $transactions[0];
        $this->assertEquals($transaction->id, $txn['id']);
        $this->assertEquals('credit', $txn['type']);
        $this->assertEquals(5000.00, $txn['amount']);
        $this->assertEquals(10000.00, $txn['balance_before']);
        $this->assertEquals(15000.00, $txn['balance_after']);
        $this->assertEquals('Wallet funding', $txn['purpose']);
        $this->assertEquals('TXN-20240315-ABC12345', $txn['reference']);
        $this->assertEquals('successful', $txn['status']);
    }

    // ============ GET /api/v1/wallet/transactions/{id} Tests ============

    public function test_requires_authentication_to_get_transaction_details(): void
    {
        $transaction = WalletTransaction::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/wallet/transactions/{$transaction->id}");

        $response->assertUnauthorized();
    }

    public function test_returns_transaction_details(): void
    {
        $transaction = WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 5000.00,
            'balance_before' => 10000.00,
            'balance_after' => 15000.00,
            'purpose' => 'Wallet funding',
            'reference' => 'TXN-20240315-ABC12345',
            'status' => 'successful',
            'metadata' => ['payment_method' => 'paystack'],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/wallet/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'type',
                    'amount',
                    'balance_before',
                    'balance_after',
                    'purpose',
                    'reference',
                    'metadata',
                    'status',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.type', 'credit')
            ->assertJsonPath('data.amount', 5000.00)
            ->assertJsonPath('data.balance_before', 10000.00)
            ->assertJsonPath('data.balance_after', 15000.00)
            ->assertJsonPath('data.purpose', 'Wallet funding')
            ->assertJsonPath('data.reference', 'TXN-20240315-ABC12345')
            ->assertJsonPath('data.status', 'successful');
    }

    public function test_returns_404_for_nonexistent_transaction(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions/99999');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Transaction not found');
    }

    public function test_prevents_accessing_other_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        $transaction = WalletTransaction::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/wallet/transactions/{$transaction->id}");

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Transaction not found');
    }

    public function test_transaction_details_includes_metadata(): void
    {
        $metadata = [
            'payment_method' => 'paystack',
            'initiated_at' => now()->toIso8601String(),
        ];

        $transaction = WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'metadata' => $metadata,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/wallet/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('data.metadata.payment_method', 'paystack');
    }

    public function test_transaction_details_includes_timestamps(): void
    {
        $transaction = WalletTransaction::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/wallet/transactions/{$transaction->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['updated_at']);
    }

    public function test_transaction_details_for_debit_transaction(): void
    {
        $transaction = WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'debit',
            'amount' => 2000.00,
            'balance_before' => 15000.00,
            'balance_after' => 13000.00,
            'purpose' => 'Contribution payment',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/wallet/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('data.type', 'debit')
            ->assertJsonPath('data.amount', 2000.00)
            ->assertJsonPath('data.balance_before', 15000.00)
            ->assertJsonPath('data.balance_after', 13000.00);
    }

    // ============ Authorization Tests ============

    public function test_suspended_user_cannot_get_balance(): void
    {
        $this->user->update(['status' => 'suspended']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/balance');

        $response->assertForbidden();
    }

    public function test_suspended_user_cannot_get_transactions(): void
    {
        $this->user->update(['status' => 'suspended']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertForbidden();
    }

    public function test_suspended_user_cannot_get_transaction_details(): void
    {
        $this->user->update(['status' => 'suspended']);
        $transaction = WalletTransaction::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/wallet/transactions/{$transaction->id}");

        $response->assertForbidden();
    }

    // ============ Edge Cases and Boundary Tests ============

    public function test_balance_with_large_amount(): void
    {
        $this->user->update(['wallet_balance' => 999999999.99]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/balance');

        $response->assertOk()
            ->assertJsonPath('data.balance', 999999999.99);
    }

    public function test_transactions_pagination_with_single_page(): void
    {
        WalletTransaction::factory(5)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonPath('data.pagination.last_page', 1)
            ->assertJsonPath('data.pagination.current_page', 1);

        $this->assertCount(5, $response->json('data.transactions'));
    }

    public function test_transactions_with_various_statuses(): void
    {
        WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'successful',
        ]);

        WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        WalletTransaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 3);

        $transactions = $response->json('data.transactions');
        $statuses = array_column($transactions, 'status');
        $this->assertContains('successful', $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertContains('failed', $statuses);
    }
}
