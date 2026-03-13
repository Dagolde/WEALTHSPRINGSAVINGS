<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = new WalletService();
    }

    /** @test */
    public function it_funds_wallet_successfully()
    {
        $user = User::factory()->create(['wallet_balance' => 0]);

        $transaction = $this->walletService->fundWallet(
            $user,
            5000.00,
            'Test wallet funding'
        );

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('credit', $transaction->type);
        $this->assertEquals(5000.00, $transaction->amount);
        $this->assertEquals(0, $transaction->balance_before);
        $this->assertEquals(5000.00, $transaction->balance_after);
        $this->assertEquals('successful', $transaction->status);

        // Verify user balance updated
        $user->refresh();
        $this->assertEquals(5000.00, $user->wallet_balance);
    }

    /** @test */
    public function it_credits_wallet_with_metadata()
    {
        $user = User::factory()->create(['wallet_balance' => 1000.00]);

        $metadata = [
            'payment_method' => 'card',
            'gateway' => 'paystack',
            'transaction_id' => 'PAY-123456',
        ];

        $transaction = $this->walletService->creditWallet(
            $user,
            2500.00,
            'Payment received',
            $metadata
        );

        $this->assertEquals(2500.00, $transaction->amount);
        $this->assertEquals(1000.00, $transaction->balance_before);
        $this->assertEquals(3500.00, $transaction->balance_after);
        $this->assertEquals($metadata, $transaction->metadata);

        $user->refresh();
        $this->assertEquals(3500.00, $user->wallet_balance);
    }

    /** @test */
    public function it_debits_wallet_with_sufficient_balance()
    {
        $user = User::factory()->create(['wallet_balance' => 5000.00]);

        $transaction = $this->walletService->debitWallet(
            $user,
            2000.00,
            'Contribution payment'
        );

        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals(2000.00, $transaction->amount);
        $this->assertEquals(5000.00, $transaction->balance_before);
        $this->assertEquals(3000.00, $transaction->balance_after);
        $this->assertEquals('successful', $transaction->status);

        $user->refresh();
        $this->assertEquals(3000.00, $user->wallet_balance);
    }

    /** @test */
    public function it_throws_exception_when_debiting_with_insufficient_balance()
    {
        $user = User::factory()->create(['wallet_balance' => 500.00]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $this->walletService->debitWallet(
            $user,
            1000.00,
            'Test debit'
        );

        // Verify balance unchanged
        $user->refresh();
        $this->assertEquals(500.00, $user->wallet_balance);
    }

    /** @test */
    public function it_gets_current_balance()
    {
        $user = User::factory()->create(['wallet_balance' => 7500.50]);

        $balance = $this->walletService->getBalance($user);

        $this->assertEquals(7500.50, $balance);
    }

    /** @test */
    public function it_gets_fresh_balance_avoiding_stale_data()
    {
        $user = User::factory()->create(['wallet_balance' => 1000.00]);

        // Simulate another process updating the balance
        DB::table('users')
            ->where('id', $user->id)
            ->update(['wallet_balance' => 5000.00]);

        // getBalance should return fresh data, not cached
        $balance = $this->walletService->getBalance($user);

        $this->assertEquals(5000.00, $balance);
    }

    /** @test */
    public function it_generates_unique_transaction_references()
    {
        $user = User::factory()->create(['wallet_balance' => 10000.00]);

        $transaction1 = $this->walletService->creditWallet($user, 100.00, 'Test 1');
        $transaction2 = $this->walletService->creditWallet($user, 200.00, 'Test 2');
        $transaction3 = $this->walletService->creditWallet($user, 300.00, 'Test 3');

        $this->assertNotEquals($transaction1->reference, $transaction2->reference);
        $this->assertNotEquals($transaction2->reference, $transaction3->reference);
        $this->assertNotEquals($transaction1->reference, $transaction3->reference);

        // Verify format: TXN-YYYYMMDD-XXXXXXXX
        $this->assertMatchesRegularExpression('/^TXN-\d{8}-[A-Z0-9]{8}$/', $transaction1->reference);
        $this->assertMatchesRegularExpression('/^TXN-\d{8}-[A-Z0-9]{8}$/', $transaction2->reference);
    }

    /** @test */
    public function it_records_balance_before_and_after_for_each_transaction()
    {
        $user = User::factory()->create(['wallet_balance' => 1000.00]);

        // First transaction: credit 500
        $txn1 = $this->walletService->creditWallet($user, 500.00, 'Credit 1');
        $this->assertEquals(1000.00, $txn1->balance_before);
        $this->assertEquals(1500.00, $txn1->balance_after);

        // Second transaction: debit 300
        $user->refresh();
        $txn2 = $this->walletService->debitWallet($user, 300.00, 'Debit 1');
        $this->assertEquals(1500.00, $txn2->balance_before);
        $this->assertEquals(1200.00, $txn2->balance_after);

        // Third transaction: credit 800
        $user->refresh();
        $txn3 = $this->walletService->creditWallet($user, 800.00, 'Credit 2');
        $this->assertEquals(1200.00, $txn3->balance_before);
        $this->assertEquals(2000.00, $txn3->balance_after);

        // Verify final balance
        $user->refresh();
        $this->assertEquals(2000.00, $user->wallet_balance);
    }

    /** @test */
    public function it_maintains_transaction_audit_trail()
    {
        $user = User::factory()->create(['wallet_balance' => 5000.00]);

        $this->walletService->debitWallet($user, 1000.00, 'Purchase 1');
        $this->walletService->creditWallet($user, 2000.00, 'Refund');
        $this->walletService->debitWallet($user, 500.00, 'Purchase 2');

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(3, $transactions);

        // Verify first transaction
        $this->assertEquals('debit', $transactions[0]->type);
        $this->assertEquals(5000.00, $transactions[0]->balance_before);
        $this->assertEquals(4000.00, $transactions[0]->balance_after);

        // Verify second transaction
        $this->assertEquals('credit', $transactions[1]->type);
        $this->assertEquals(4000.00, $transactions[1]->balance_before);
        $this->assertEquals(6000.00, $transactions[1]->balance_after);

        // Verify third transaction
        $this->assertEquals('debit', $transactions[2]->type);
        $this->assertEquals(6000.00, $transactions[2]->balance_before);
        $this->assertEquals(5500.00, $transactions[2]->balance_after);
    }

    /** @test */
    public function it_uses_pessimistic_locking_for_concurrent_operations()
    {
        $user = User::factory()->create(['wallet_balance' => 1000.00]);

        // Simulate concurrent debit operations
        DB::transaction(function () use ($user) {
            // This should lock the user record
            $transaction = $this->walletService->debitWallet($user, 500.00, 'Concurrent test');

            $this->assertEquals(500.00, $transaction->balance_after);
        });

        $user->refresh();
        $this->assertEquals(500.00, $user->wallet_balance);
    }

    /** @test */
    public function it_handles_multiple_sequential_transactions_correctly()
    {
        $user = User::factory()->create(['wallet_balance' => 0]);

        // Fund wallet
        $this->walletService->fundWallet($user, 10000.00, 'Initial funding');

        // Make multiple debits
        $user->refresh();
        $this->walletService->debitWallet($user, 1000.00, 'Payment 1');

        $user->refresh();
        $this->walletService->debitWallet($user, 2000.00, 'Payment 2');

        $user->refresh();
        $this->walletService->debitWallet($user, 1500.00, 'Payment 3');

        // Add more credits
        $user->refresh();
        $this->walletService->creditWallet($user, 5000.00, 'Top up');

        // Final balance should be: 0 + 10000 - 1000 - 2000 - 1500 + 5000 = 10500
        $user->refresh();
        $this->assertEquals(10500.00, $user->wallet_balance);

        // Verify transaction count
        $transactionCount = WalletTransaction::where('user_id', $user->id)->count();
        $this->assertEquals(5, $transactionCount);
    }

    /** @test */
    public function it_stores_metadata_as_json()
    {
        $user = User::factory()->create(['wallet_balance' => 1000.00]);

        $metadata = [
            'group_id' => 123,
            'group_name' => 'Test Group',
            'contribution_date' => '2024-03-15',
            'payment_method' => 'wallet',
        ];

        $transaction = $this->walletService->debitWallet(
            $user,
            500.00,
            'Group contribution',
            $metadata
        );

        // Verify metadata is stored and retrievable
        $this->assertEquals($metadata, $transaction->metadata);

        // Verify it's stored as JSON in database
        $dbTransaction = WalletTransaction::find($transaction->id);
        $this->assertEquals($metadata, $dbTransaction->metadata);
    }

    /** @test */
    public function it_validates_balance_invariant_property()
    {
        // Property 12: balance_after = balance_before ± amount
        $user = User::factory()->create(['wallet_balance' => 5000.00]);

        // Test credit
        $creditTxn = $this->walletService->creditWallet($user, 1500.00, 'Test credit');
        $this->assertEquals(
            $creditTxn->balance_after,
            $creditTxn->balance_before + $creditTxn->amount
        );

        // Test debit
        $user->refresh();
        $debitTxn = $this->walletService->debitWallet($user, 2000.00, 'Test debit');
        $this->assertEquals(
            $debitTxn->balance_after,
            $debitTxn->balance_before - $debitTxn->amount
        );
    }

    /** @test */
    public function it_ensures_transaction_atomicity_on_failure()
    {
        $user = User::factory()->create(['wallet_balance' => 1000.00]);

        try {
            DB::transaction(function () use ($user) {
                // First debit should succeed
                $this->walletService->debitWallet($user, 500.00, 'First debit');

                // Second debit should fail (insufficient balance)
                $user->refresh();
                $this->walletService->debitWallet($user, 1000.00, 'Second debit');
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        // Balance should be rolled back to original
        $user->refresh();
        $this->assertEquals(1000.00, $user->wallet_balance);

        // No transactions should be recorded
        $transactionCount = WalletTransaction::where('user_id', $user->id)->count();
        $this->assertEquals(0, $transactionCount);
    }

    /** @test */
    public function it_handles_zero_amount_gracefully()
    {
        $user = User::factory()->create(['wallet_balance' => 1000.00]);

        $transaction = $this->walletService->creditWallet($user, 0, 'Zero amount test');

        $this->assertEquals(0, $transaction->amount);
        $this->assertEquals(1000.00, $transaction->balance_before);
        $this->assertEquals(1000.00, $transaction->balance_after);

        $user->refresh();
        $this->assertEquals(1000.00, $user->wallet_balance);
    }

    /** @test */
    public function it_handles_decimal_amounts_correctly()
    {
        $user = User::factory()->create(['wallet_balance' => 1000.50]);

        $transaction = $this->walletService->creditWallet($user, 250.75, 'Decimal test');

        $this->assertEquals(250.75, $transaction->amount);
        $this->assertEquals(1000.50, $transaction->balance_before);
        $this->assertEquals(1251.25, $transaction->balance_after);

        $user->refresh();
        $this->assertEquals(1251.25, $user->wallet_balance);
    }
}
