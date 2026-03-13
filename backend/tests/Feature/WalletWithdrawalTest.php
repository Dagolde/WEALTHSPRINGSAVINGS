<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with wallet balance
        $this->user = User::factory()->create([
            'wallet_balance' => 50000.00,
            'status' => 'active',
        ]);

        // Create a bank account for the user
        $this->bankAccount = BankAccount::factory()->create([
            'user_id' => $this->user->id,
            'is_verified' => true,
            'is_primary' => true,
        ]);
    }

    public function test_requires_authentication_to_withdraw(): void
    {
        $response = $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_validates_amount_is_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('amount');
    }

    public function test_validates_amount_is_numeric(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 'not-a-number',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('amount');
    }

    public function test_validates_minimum_withdrawal_amount(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 50,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('amount');
    }

    public function test_validates_maximum_withdrawal_amount(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 10000001,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('amount');
    }

    public function test_validates_bank_account_id_is_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('bank_account_id');
    }

    public function test_validates_bank_account_id_is_integer(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => 'not-an-id',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('bank_account_id');
    }

    public function test_validates_bank_account_exists(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => 99999,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('bank_account_id');
    }

    public function test_rejects_withdrawal_to_other_users_bank_account(): void
    {
        $otherUser = User::factory()->create(['wallet_balance' => 50000.00]);
        $otherBankAccount = BankAccount::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $otherBankAccount->id,
        ]);

        $response->assertBadRequest();
        $response->assertJsonFragment([
            'message' => 'Bank account not found or does not belong to you',
        ]);
    }

    public function test_rejects_withdrawal_with_insufficient_balance(): void
    {
        $this->user->update(['wallet_balance' => 1000.00]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertBadRequest();
        $this->assertStringContainsString('Insufficient wallet balance', $response->json('message'));
    }

    public function test_processes_withdrawal_with_valid_amount_and_bank_account(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'message' => 'Withdrawal initiated successfully',
            'status' => 'pending',
            'admin_approval_status' => 'pending',
        ]);
        $this->assertEquals('5000.00', $response->json('data.amount'));
    }

    public function test_creates_withdrawal_record_with_pending_status(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $withdrawal = Withdrawal::where('user_id', $this->user->id)->first();

        $this->assertNotNull($withdrawal);
        $this->assertEquals('pending', $withdrawal->status);
        $this->assertEquals('pending', $withdrawal->admin_approval_status);
        $this->assertEquals(5000, $withdrawal->amount);
        $this->assertEquals($this->bankAccount->id, $withdrawal->bank_account_id);
    }

    public function test_debits_wallet_immediately(): void
    {
        $initialBalance = $this->user->wallet_balance;

        $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $this->user->refresh();

        $this->assertEquals($initialBalance - 5000, $this->user->wallet_balance);
    }

    public function test_creates_wallet_transaction_record(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $transaction = WalletTransaction::where('user_id', $this->user->id)
            ->where('type', 'debit')
            ->where('purpose', 'Wallet withdrawal')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(5000, $transaction->amount);
        $this->assertEquals('successful', $transaction->status);
        $this->assertEquals(50000, $transaction->balance_before);
        $this->assertEquals(45000, $transaction->balance_after);
    }

    public function test_generates_unique_withdrawal_reference(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 3000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $withdrawals = Withdrawal::where('user_id', $this->user->id)->get();

        $this->assertEquals(2, $withdrawals->count());
        $this->assertNotEquals(
            $withdrawals[0]->payment_reference,
            $withdrawals[1]->payment_reference
        );
    }

    public function test_returns_withdrawal_details_in_response(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'user_id',
                'bank_account_id',
                'amount',
                'status',
                'admin_approval_status',
                'payment_reference',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_accepts_amount_at_minimum_boundary(): void
    {
        $this->user->update(['wallet_balance' => 100.00]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 100,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertCreated();
    }

    public function test_accepts_amount_at_maximum_boundary(): void
    {
        $this->user->update(['wallet_balance' => 10000000.00]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 10000000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertCreated();
    }

    public function test_processes_multiple_withdrawals(): void
    {
        $this->user->update(['wallet_balance' => 50000.00]);

        // First withdrawal
        $response1 = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response1->assertCreated();

        // Second withdrawal
        $response2 = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 3000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response2->assertCreated();

        // Verify both withdrawals exist
        $withdrawals = Withdrawal::where('user_id', $this->user->id)->get();
        $this->assertEquals(2, $withdrawals->count());

        // Verify wallet balance is correct
        $this->user->refresh();
        $this->assertEquals(42000, $this->user->wallet_balance);
    }

    public function test_prevents_withdrawal_exceeding_balance_with_concurrent_requests(): void
    {
        $this->user->update(['wallet_balance' => 10000.00]);

        // First withdrawal for 6000
        $response1 = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 6000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response1->assertCreated();

        // Second withdrawal for 5000 should fail (only 4000 left)
        $response2 = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response2->assertBadRequest();
        $this->assertStringContainsString('Insufficient wallet balance', $response2->json('message'));
    }

    public function test_stores_bank_account_metadata_in_transaction(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $transaction = WalletTransaction::where('user_id', $this->user->id)
            ->where('type', 'debit')
            ->first();

        $this->assertNotNull($transaction->metadata);
        $this->assertEquals($this->bankAccount->id, $transaction->metadata['bank_account_id']);
        $this->assertEquals($this->bankAccount->bank_name, $transaction->metadata['bank_name']);
    }

    public function test_includes_bank_account_in_withdrawal_response(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'bank_account' => [
                    'id',
                    'account_name',
                    'account_number',
                    'bank_name',
                ],
            ],
        ]);
    }

    public function test_validates_withdrawal_amount_against_wallet_balance(): void
    {
        // Property 14: Withdrawal Validation
        // For any withdrawal request with amount A from a user with wallet balance B,
        // the system should reject the request if A > B and only process it if A <= B.

        $this->user->update(['wallet_balance' => 5000.00]);

        // Test: A > B (should reject)
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 6000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertBadRequest();

        // Test: A <= B (should process)
        $response = $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertCreated();

        // Verify withdrawal was created
        $withdrawal = Withdrawal::where('user_id', $this->user->id)->first();
        $this->assertNotNull($withdrawal);
        $this->assertEquals(5000, $withdrawal->amount);
    }

    public function test_maintains_balance_invariant_after_withdrawal(): void
    {
        // Property 12: Wallet Balance Invariant
        // The sum of all wallet transactions should equal the current balance

        $initialBalance = $this->user->wallet_balance;

        $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $this->user->refresh();

        // Verify balance_after in transaction matches current balance
        $transaction = WalletTransaction::where('user_id', $this->user->id)
            ->where('type', 'debit')
            ->first();

        $this->assertEquals($this->user->wallet_balance, $transaction->balance_after);
        $this->assertEquals($initialBalance - 5000, $this->user->wallet_balance);
    }

    public function test_creates_audit_trail_for_withdrawal(): void
    {
        // Property 13: Wallet Transaction Audit Trail
        // Every wallet transaction should have balance_before and balance_after recorded

        $this->actingAs($this->user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000,
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $transaction = WalletTransaction::where('user_id', $this->user->id)
            ->where('type', 'debit')
            ->first();

        $this->assertNotNull($transaction->balance_before);
        $this->assertNotNull($transaction->balance_after);
        $this->assertEquals(50000, $transaction->balance_before);
        $this->assertEquals(45000, $transaction->balance_after);
        $this->assertEquals($transaction->balance_before - $transaction->amount, $transaction->balance_after);
    }
}

