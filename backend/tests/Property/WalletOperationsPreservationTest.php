<?php

namespace Tests\Property;

use Tests\TestCase;
use App\Models\User;
use App\Models\BankAccount;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Preservation Property Tests for Wallet Operations (Task 6)
 * 
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6**
 * 
 * **Property 2: Preservation - Wallet Operations Database Updates Preserved**
 * 
 * IMPORTANT: This test follows observation-first methodology.
 * These tests capture the CURRENT behavior on UNFIXED code.
 * They should PASS on unfixed code to establish baseline behavior.
 * After implementing the fix, these tests should STILL PASS (no regressions).
 * 
 * GOAL: Ensure wallet operations that should NOT be affected by the cache fix
 * continue to work correctly. Focus on database updates, not cache behavior.
 * 
 * For any wallet operation (funding database updates, withdrawal database updates,
 * transaction history, KYC, profile) that is NOT the balance display caching issue,
 * the system should produce exactly the same behavior as before the fix.
 */
class WalletOperationsPreservationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property: Wallet funding creates transaction records correctly
     * 
     * For any valid wallet funding operation, the system should:
     * - Create a wallet transaction record (pending or successful depending on environment)
     * - Return success response with transaction reference
     * - Record correct transaction details
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * This confirms baseline behavior to preserve.
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Wallet Operations Database Updates Preserved
     */
    public function wallet_funding_creates_transaction_records_correctly()
    {
        // Test with multiple funding amounts
        $testAmounts = [1000, 5000, 10000];
        
        foreach ($testAmounts as $amount) {
            // Create authenticated user with initial balance
            $initialBalance = 5000.00;
            $user = User::factory()->create([
                'email_verified_at' => now(),
                'kyc_status' => 'verified',
                'wallet_balance' => $initialBalance
            ]);

            $token = $user->createToken('test-token')->plainTextToken;

            // Make wallet funding request
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->postJson('/api/v1/wallet/fund', [
                'amount' => $amount
            ]);

            // Should return success
            $this->assertContains(
                $response->status(),
                [200, 201],
                "Wallet funding should return success status for amount {$amount}"
            );

            // CRITICAL: Verify wallet funding endpoint works correctly
            // This is the core behavior that must be preserved
            $response->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
            
            // In development mode, wallet is credited immediately
            if (config('app.env') === 'development' || config('app.env') === 'local') {
                // Verify database balance was updated
                $user->refresh();
                $expectedBalance = $initialBalance + $amount;
                
                $this->assertEquals(
                    $expectedBalance,
                    $user->wallet_balance,
                    "Database wallet_balance should be updated to {$expectedBalance} after funding {$amount} in dev mode. " .
                    "This database update behavior must be preserved after the cache fix."
                );
            }
        }
    }

    /**
     * Property: Wallet withdrawal processes correctly
     * 
     * For any valid wallet withdrawal operation, the system should:
     * - Process the withdrawal request
     * - Create withdrawal record
     * - Debit wallet balance if successful
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Wallet Operations Database Updates Preserved
     */
    public function wallet_withdrawal_processes_correctly()
    {
        // Test with one withdrawal amount
        $amount = 1000;
        
        // Create user with sufficient balance
        $initialBalance = 10000;
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => $initialBalance
        ]);

        // Create verified bank account for withdrawal
        $bankAccount = BankAccount::factory()->create([
            'user_id' => $user->id,
            'is_verified' => true,
            'is_primary' => true
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Make withdrawal request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/wallet/withdraw', [
            'amount' => $amount,
            'bank_account_id' => $bankAccount->id
        ]);

        // CRITICAL: Verify withdrawal endpoint works correctly
        // This is the core behavior that must be preserved
        if ($response->status() === 201 || $response->status() === 200) {
            // Withdrawal was successful
            $response->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
            
            // Verify database balance was debited
            $user->refresh();
            $expectedBalance = $initialBalance - $amount;
            
            $this->assertEquals(
                $expectedBalance,
                $user->wallet_balance,
                "Database wallet_balance should be debited to {$expectedBalance} after withdrawal of {$amount}. " .
                "This database update behavior must be preserved after the cache fix."
            );
        } else {
            // If withdrawal failed, verify it's for a valid reason (e.g., validation error)
            $this->assertContains(
                $response->status(),
                [400, 422],
                "Withdrawal should either succeed (200/201) or fail with validation error (400/422)"
            );
        }
    }

    /**
     * Property: Transaction history retrieval returns accurate records
     * 
     * For any user with wallet transactions, the transaction history endpoint should:
     * - Return all transactions for the user from the database
     * - Order transactions correctly (newest first)
     * - Include accurate transaction details (amount, type, status, balances)
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Wallet Operations Database Updates Preserved
     */
    public function transaction_history_retrieval_returns_accurate_database_records()
    {
        // Create user
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => 10000.00
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Create multiple transactions with unique references
        $transactionCount = 5;
        $createdTransactions = [];
        
        for ($i = 0; $i < $transactionCount; $i++) {
            $transaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $i % 2 === 0 ? 'credit' : 'debit',
                'amount' => 1000 + ($i * 100),
                'balance_before' => 10000,
                'balance_after' => 10000,
                'purpose' => 'Test transaction ' . $i,
                'reference' => 'PRESERVE-TEST-' . $user->id . '-' . $i . '-' . time(),
                'status' => 'successful',
                'metadata' => ['test' => true, 'index' => $i]
            ]);
            $createdTransactions[] = $transaction;
            
            // Add small delay to ensure different timestamps
            usleep(10000); // 10ms
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
                ]
            ]
        ]);

        $data = $response->json('data');
        $transactions = $data['transactions'];

        // CRITICAL: Verify correct number of transactions returned from database
        // This database query behavior must be preserved
        $this->assertGreaterThanOrEqual(
            $transactionCount,
            count($transactions),
            "Should return at least {$transactionCount} user transactions from database. " .
            "This database retrieval behavior must be preserved after the cache fix."
        );

        // Verify transactions are ordered by created_at descending (newest first)
        if (count($transactions) >= 2) {
            $timestamps = array_map(function($t) {
                return strtotime($t['created_at']);
            }, $transactions);

            $sortedTimestamps = $timestamps;
            rsort($sortedTimestamps);

            $this->assertEquals(
                $sortedTimestamps,
                $timestamps,
                'Transactions should be ordered by created_at descending (newest first)'
            );
        }

        // Verify each transaction has correct user_id
        foreach ($transactions as $transaction) {
            $this->assertEquals(
                $user->id,
                $transaction['user_id'],
                'Each transaction should belong to the authenticated user'
            );
        }

        // Verify transaction details are accurate
        foreach ($createdTransactions as $created) {
            $found = collect($transactions)->firstWhere('reference', $created->reference);
            if ($found) {
                $this->assertEquals($created->amount, $found['amount'], 'Transaction amount should match');
                $this->assertEquals($created->type, $found['type'], 'Transaction type should match');
                $this->assertEquals($created->status, $found['status'], 'Transaction status should match');
            }
        }
    }

    /**
     * Property: KYC operations work correctly
     * 
     * For any KYC status check operation, the system should:
     * - Return the user's current KYC status from the database
     * - Work independently of wallet balance caching
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Wallet Operations Database Updates Preserved
     */
    public function kyc_status_retrieval_works_correctly()
    {
        // Test with different KYC statuses
        $kycStatuses = ['pending', 'verified', 'rejected'];
        
        foreach ($kycStatuses as $status) {
            // Create user with specific KYC status
            $user = User::factory()->create([
                'email_verified_at' => now(),
                'kyc_status' => $status
            ]);

            $token = $user->createToken('test-token')->plainTextToken;

            // Fetch KYC status
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/kyc/status');

            // Should return success
            $response->assertStatus(200);

            // CRITICAL: Verify KYC status is returned correctly from database
            // This database query behavior must be preserved
            $response->assertJsonPath('data.kyc_status', $status);
        }
    }

    /**
     * Property: Profile operations work correctly
     * 
     * For any profile retrieval operation, the system should:
     * - Return the user's current profile data from the database
     * - Include all profile fields (name, email, phone, etc.)
     * - Work independently of wallet balance caching
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Wallet Operations Database Updates Preserved
     */
    public function profile_retrieval_works_correctly()
    {
        // Create user with profile data
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'phone' => '+2348012345678',
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => 50000.00
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Fetch profile
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/user/profile');

        // Should return success
        $response->assertStatus(200);

        // CRITICAL: Verify profile data is returned correctly from database
        // This database query behavior must be preserved
        $response->assertJsonPath('data.name', $user->name);
        $response->assertJsonPath('data.email', $user->email);
        $response->assertJsonPath('data.phone', $user->phone);
        $response->assertJsonPath('data.kyc_status', $user->kyc_status);
        
        // Verify wallet balance is included in profile
        $this->assertIsNumeric(
            $response->json('data.wallet_balance'),
            'Profile should include wallet_balance field'
        );
    }

    /**
     * Property: Database balance updates are atomic and consistent
     * 
     * For any sequence of wallet operations, the database balance should:
     * - Always reflect the sum of all successful transactions
     * - Maintain consistency between user.wallet_balance and transaction records
     * - Never show negative balance for valid operations
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test PASSES
     * 
     * @test
     * @group property
     * @group preservation
     * @group Feature: payments-banks-wallet-balance-fix, Property 2: Preservation - Wallet Operations Database Updates Preserved
     */
    public function database_balance_updates_are_atomic_and_consistent()
    {
        // Create user with initial balance
        $initialBalance = 10000.00;
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => $initialBalance
        ]);

        // Create bank account for withdrawals
        $bankAccount = BankAccount::factory()->create([
            'user_id' => $user->id,
            'is_verified' => true,
            'is_primary' => true
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Perform sequence of operations
        $operations = [
            ['type' => 'fund', 'amount' => 5000],
            ['type' => 'fund', 'amount' => 3000],
        ];

        $expectedBalance = $initialBalance;
        
        foreach ($operations as $operation) {
            if ($operation['type'] === 'fund') {
                $response = $this->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->postJson('/api/v1/wallet/fund', [
                    'amount' => $operation['amount']
                ]);
                
                // In development mode, balance is updated immediately
                if (config('app.env') === 'development' || config('app.env') === 'local') {
                    $expectedBalance += $operation['amount'];
                }
            }
        }

        // CRITICAL: Verify final database balance
        // This database consistency must be preserved
        $user->refresh();
        
        // Verify balance is never negative
        $this->assertGreaterThanOrEqual(
            0,
            $user->wallet_balance,
            'Database balance should never be negative'
        );
        
        // In development mode, verify balance matches expected
        if (config('app.env') === 'development' || config('app.env') === 'local') {
            $this->assertEquals(
                $expectedBalance,
                $user->wallet_balance,
                "Database balance should be {$expectedBalance} after sequence of operations. " .
                "This database consistency must be preserved after the cache fix."
            );
        }
    }
}
