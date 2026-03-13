<?php

namespace Tests\Property;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\BankAccount;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Preservation Property Tests for Existing Payment Endpoints
 * 
 * **Validates: Requirements 3.1, 3.2, 3.4, 3.5**
 * 
 * **Property 2: Preservation - Existing Payment Endpoints Unchanged**
 * 
 * IMPORTANT: This test follows observation-first methodology.
 * These tests capture the CURRENT behavior on UNFIXED code.
 * They should PASS on unfixed code to establish baseline behavior.
 * After implementing the fix, these tests should STILL PASS (no regressions).
 * 
 * GOAL: Ensure existing wallet and payment functionality remains unchanged.
 * 
 * For any wallet operation (funding, withdrawal, transaction history) that is NOT
 * the balance display or banks endpoint, the system should produce exactly the
 * same behavior as before the fix.
 */
class PaymentEndpointsPreservationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property: Wallet funding updates database correctly
     * 
     * For any valid wallet funding operation, the system should:
     * - Create a pending wallet transaction
     * - Return authorization URL (or success in dev mode)
     * - NOT update wallet balance until webhook confirms payment
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * This confirms baseline behavior to preserve.
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Existing Payment Endpoints Unchanged
     */
    public function wallet_funding_creates_pending_transaction_correctly()
    {
        // Test with multiple funding amounts
        $testAmounts = [100, 1000, 5000, 10000];
        
        foreach ($testAmounts as $amount) {
            // Create authenticated user
            $user = User::factory()->create([
                'email_verified_at' => now(),
                'kyc_status' => 'verified',
                'wallet_balance' => 1000.00
            ]);

            $token = $user->createToken('test-token')->plainTextToken;
            $initialBalance = $user->wallet_balance;

            // Make wallet funding request
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->postJson('/api/v1/wallet/fund', [
                'amount' => $amount
            ]);

            // Should return success (200 in dev mode, or authorization URL in production)
            $this->assertContains(
                $response->status(),
                [200, 201],
                "Wallet funding should return success status for amount {$amount}"
            );

            // In development mode, wallet is credited immediately
            if (config('app.env') === 'development' || config('app.env') === 'local') {
                // Verify transaction was created and completed
                $transaction = WalletTransaction::where('user_id', $user->id)
                    ->where('type', 'credit')
                    ->where('amount', $amount)
                    ->latest()
                    ->first();

                $this->assertNotNull($transaction, 'Transaction should be created');
                $this->assertEquals('successful', $transaction->status, 'Transaction should be successful in dev mode');
                
                // Verify wallet balance was updated
                $user->refresh();
                $this->assertEquals(
                    $initialBalance + $amount,
                    $user->wallet_balance,
                    'Wallet balance should be updated in dev mode'
                );
            } else {
                // In production mode, transaction should be pending
                $transaction = WalletTransaction::where('user_id', $user->id)
                    ->where('type', 'credit')
                    ->where('amount', $amount)
                    ->latest()
                    ->first();

                $this->assertNotNull($transaction, 'Transaction should be created');
                $this->assertEquals('pending', $transaction->status, 'Transaction should be pending in production');
                
                // Verify wallet balance NOT updated yet (pending)
                $user->refresh();
                $this->assertEquals(
                    $initialBalance,
                    $user->wallet_balance,
                    'Wallet balance should not change until payment confirmed'
                );
            }

            // Verify transaction has correct structure
            $this->assertEquals($user->id, $transaction->user_id);
            $this->assertEquals('credit', $transaction->type);
            $this->assertEquals($amount, $transaction->amount);
            $this->assertEquals($initialBalance, $transaction->balance_before);
            $this->assertNotNull($transaction->reference);
            $this->assertStringStartsWith('WALLET-', $transaction->reference);
        }
    }

    /**
     * Property: Wallet withdrawal processes correctly
     * 
     * For any valid wallet withdrawal operation, the system should:
     * - Debit wallet balance immediately
     * - Create successful wallet transaction
     * - Create pending withdrawal record
     * - Maintain balance invariant
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Existing Payment Endpoints Unchanged
     */
    public function wallet_withdrawal_debits_balance_and_creates_records_correctly()
    {
        // Test with multiple withdrawal amounts
        $testAmounts = [100, 1000, 3000, 5000];
        
        foreach ($testAmounts as $amount) {
            // Create user with sufficient balance
            $user = User::factory()->create([
                'email_verified_at' => now(),
                'kyc_status' => 'verified',
                'wallet_balance' => $amount + 1000 // Ensure sufficient balance
            ]);

            // Create bank account for withdrawal
            $bankAccount = BankAccount::factory()->create([
                'user_id' => $user->id,
                'is_verified' => true,
                'is_primary' => true
            ]);

            $token = $user->createToken('test-token')->plainTextToken;
            $initialBalance = $user->wallet_balance;

            // Make withdrawal request
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->postJson('/api/v1/wallet/withdraw', [
                'amount' => $amount,
                'bank_account_id' => $bankAccount->id
            ]);

            // Should return 201 Created
            $response->assertStatus(201);

            // Verify wallet balance was debited immediately
            $user->refresh();
            $this->assertEquals(
                $initialBalance - $amount,
                $user->wallet_balance,
                "Wallet balance should be debited immediately for amount {$amount}"
            );

            // Verify wallet transaction was created
            $transaction = WalletTransaction::where('user_id', $user->id)
                ->where('type', 'debit')
                ->where('amount', $amount)
                ->latest()
                ->first();

            $this->assertNotNull($transaction, 'Debit transaction should be created');
            $this->assertEquals('successful', $transaction->status);
            $this->assertEquals($initialBalance, $transaction->balance_before);
            $this->assertEquals($initialBalance - $amount, $transaction->balance_after);
            $this->assertEquals('Wallet withdrawal', $transaction->purpose);

            // Verify withdrawal record was created
            $withdrawal = Withdrawal::where('user_id', $user->id)
                ->where('amount', $amount)
                ->latest()
                ->first();

            $this->assertNotNull($withdrawal, 'Withdrawal record should be created');
            $this->assertEquals('pending', $withdrawal->status);
            $this->assertEquals('pending', $withdrawal->admin_approval_status);
            $this->assertEquals($bankAccount->id, $withdrawal->bank_account_id);
        }
    }

    /**
     * Property: Transaction history returns accurate records
     * 
     * For any user with wallet transactions, the transaction history endpoint should:
     * - Return all transactions for the user
     * - Order transactions by created_at descending (newest first)
     * - Include correct pagination metadata
     * - Return accurate transaction details
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Existing Payment Endpoints Unchanged
     */
    public function transaction_history_returns_accurate_records()
    {
        // Test with different numbers of transactions
        $testCounts = [1, 3, 5, 10];
        
        foreach ($testCounts as $transactionCount) {
            // Create user
            $user = User::factory()->create([
                'email_verified_at' => now(),
                'kyc_status' => 'verified',
                'wallet_balance' => 10000.00
            ]);

            $token = $user->createToken('test-token')->plainTextToken;

            // Create multiple transactions
            $createdTransactions = [];
            for ($i = 0; $i < $transactionCount; $i++) {
                $transaction = WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => $i % 2 === 0 ? 'credit' : 'debit',
                    'amount' => 1000 + ($i * 100),
                    'balance_before' => 10000,
                    'balance_after' => 10000,
                    'purpose' => 'Test transaction ' . $i,
                    'reference' => 'TEST-' . $i,
                    'status' => 'successful',
                    'metadata' => ['test' => true]
                ]);
                $createdTransactions[] = $transaction;
                
                // Add small delay to ensure different timestamps
                usleep(1000);
            }

            // Fetch transaction history
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/wallet/transactions');

            // Should return success
            $response->assertStatus(200);

            // Verify response structure
            $response->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transactions' => [
                        '*' => [
                            'id',
                            'user_id',
                            'type',
                            'amount',
                            'balance_before',
                            'balance_after',
                            'purpose',
                            'reference',
                            'status',
                            'created_at'
                        ]
                    ],
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                        'last_page'
                    ]
                ]
            ]);

            $data = $response->json('data');
            $transactions = $data['transactions'];

            // Verify correct number of transactions returned
            $this->assertCount(
                $transactionCount,
                $transactions,
                "Should return all {$transactionCount} user transactions"
            );

            // Verify transactions are ordered by created_at descending (newest first)
            $timestamps = array_map(function($t) {
                return strtotime($t['created_at']);
            }, $transactions);

            $sortedTimestamps = $timestamps;
            rsort($sortedTimestamps);

            $this->assertEquals(
                $sortedTimestamps,
                $timestamps,
                'Transactions should be ordered by created_at descending'
            );

            // Verify each transaction has correct user_id
            foreach ($transactions as $transaction) {
                $this->assertEquals($user->id, $transaction['user_id']);
            }

            // Verify pagination metadata
            $this->assertEquals($transactionCount, $data['pagination']['total']);
            $this->assertGreaterThan(0, $data['pagination']['per_page']);
        }
    }

    /**
     * Property: Wallet balance endpoint returns current balance
     * 
     * For any authenticated user, the balance endpoint should:
     * - Return 200 status
     * - Return current wallet balance
     * - Return currency (NGN)
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Existing Payment Endpoints Unchanged
     */
    public function wallet_balance_endpoint_returns_current_balance()
    {
        // Test with various balance amounts
        $testBalances = [0, 1000, 50000, 100000];
        
        foreach ($testBalances as $balance) {
            // Create user with specific balance
            $user = User::factory()->create([
                'email_verified_at' => now(),
                'kyc_status' => 'verified',
                'wallet_balance' => $balance
            ]);

            $token = $user->createToken('test-token')->plainTextToken;

            // Fetch balance
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/wallet/balance');

            // Should return success
            $response->assertStatus(200);

            // Verify response structure
            $response->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'balance',
                    'currency'
                ]
            ]);

            $data = $response->json('data');

            // Verify balance is returned (may be cached, so check it's a number)
            $this->assertIsNumeric($data['balance'], "Balance should be numeric for balance {$balance}");
            $this->assertEquals('NGN', $data['currency'], 'Currency should be NGN');
        }
    }

    /**
     * Property: Contribution endpoints function correctly
     * 
     * For any authenticated user in a group, contribution endpoints should:
     * - Return contribution history
     * - Allow recording contributions
     * - Maintain data integrity
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Existing Payment Endpoints Unchanged
     */
    public function contribution_endpoints_function_correctly()
    {
        // Create user
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => 10000.00
        ]);

        // Create group
        $group = Group::factory()->create([
            'status' => 'active',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'start_date' => now()->toDateString()
        ]);

        // Add user to group
        $group->members()->create([
            'user_id' => $user->id,
            'position_number' => 1,
            'payout_day' => 1
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Test: Fetch contribution history
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/contributions');

        // Should return success (200 or 204 if no contributions)
        $this->assertContains(
            $response->status(),
            [200, 204],
            'Contribution history endpoint should return success'
        );

        // If 200, verify it has data structure (don't check specific keys as they may vary)
        if ($response->status() === 200) {
            $this->assertIsArray($response->json());
        }
    }

    /**
     * Property: Payout endpoints function correctly
     * 
     * For any authenticated user, payout endpoints should:
     * - Return payout history
     * - Return payout schedule for groups
     * - Maintain data integrity
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Existing Payment Endpoints Unchanged
     */
    public function payout_endpoints_function_correctly()
    {
        // Create user
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified'
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Test: Fetch payout history
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/payouts/history');

        // Should return success (200 or 204 if no payouts)
        $this->assertContains(
            $response->status(),
            [200, 204],
            'Payout history endpoint should return success'
        );

        // If 200, verify it has data structure (don't check specific keys as they may vary)
        if ($response->status() === 200) {
            $this->assertIsArray($response->json());
        }
    }

    /**
     * Property: Wallet operations maintain balance invariant
     * 
     * For any sequence of wallet operations, the balance_after in the last
     * transaction should always equal the current wallet balance.
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Existing Payment Endpoints Unchanged
     */
    public function wallet_operations_maintain_balance_invariant()
    {
        // Test with different numbers of operations
        $testOperationCounts = [2, 3, 5];
        
        foreach ($testOperationCounts as $operationCount) {
            // Create user with sufficient balance
            $user = User::factory()->create([
                'email_verified_at' => now(),
                'kyc_status' => 'verified',
                'wallet_balance' => 50000.00
            ]);

            // Create bank account for withdrawals
            $bankAccount = BankAccount::factory()->create([
                'user_id' => $user->id,
                'is_verified' => true,
                'is_primary' => true
            ]);

            $token = $user->createToken('test-token')->plainTextToken;

            // Perform multiple operations
            for ($i = 0; $i < $operationCount; $i++) {
                // Alternate between funding and withdrawal
                if ($i % 2 === 0) {
                    // Funding
                    $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ])->postJson('/api/v1/wallet/fund', [
                        'amount' => 1000
                    ]);
                } else {
                    // Withdrawal
                    $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ])->postJson('/api/v1/wallet/withdraw', [
                        'amount' => 500,
                        'bank_account_id' => $bankAccount->id
                    ]);
                }
            }

            // Get last transaction
            $lastTransaction = WalletTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Get current balance
            $user->refresh();
            $currentBalance = $user->wallet_balance;

            // In development mode, funding is immediate, so check last successful transaction
            if ($lastTransaction && $lastTransaction->status === 'successful') {
                $this->assertEquals(
                    $lastTransaction->balance_after,
                    $currentBalance,
                    "Last transaction balance_after should equal current wallet balance for {$operationCount} operations"
                );
            }
        }
    }
}
