<?php

namespace Tests\Property;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/**
 * Bug Condition Exploration Test for Wallet Balance Display Issue
 * 
 * **Validates: Requirements 2.3**
 * 
 * **Property 1: Bug Condition - Wallet Balance Shows ₦0.0 Despite Non-Zero Database Balance**
 * 
 * CRITICAL: This test MUST FAIL on unfixed code - failure confirms the bug exists.
 * DO NOT attempt to fix the test or the code when it fails.
 * 
 * This test encodes the EXPECTED behavior (correct balance after funding).
 * It will validate the fix when it passes after implementation.
 * 
 * GOAL: Surface counterexamples that demonstrate the stale cache bug.
 * 
 * For any user whose database wallet balance is non-zero after funding,
 * the home screen SHOULD display the correct wallet balance from the database,
 * not a stale cached value of ₦0.0.
 * 
 * On UNFIXED code, this test will FAIL showing ₦0.0 despite non-zero database balance,
 * proving the cache invalidation bug exists.
 */
class WalletBalanceBugConditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property: Wallet balance should display correctly after funding operation
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS showing ₦0.0 despite database having correct balance
     * This failure confirms the stale cache bug exists.
     * 
     * @test
     * @group property
     * @group bugfix
     * @group Feature: payments-banks-wallet-balance-fix, Property 1: Bug Condition - Wallet Balance Shows ₦0.0 Despite Non-Zero Database Balance
     */
    public function wallet_balance_should_display_correctly_after_funding()
    {
        // Create user with initial ₦0.0 balance
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => 0.0
        ]);

        // Authenticate user
        $token = $user->createToken('test-token')->plainTextToken;

        // Step 1: Get initial balance (should be ₦0.0 and will be cached)
        $initialResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance');

        $initialResponse->assertStatus(200);
        $initialBalance = $initialResponse->json('data.balance');
        $this->assertEquals(0.0, $initialBalance, 'Initial balance should be ₦0.0');

        // Step 2: Fund wallet with ₦1,015,000 (simulating User 18's scenario)
        $fundingAmount = 1015000.0;
        
        // Directly update database to simulate successful wallet funding
        // (In real scenario, this would be done by the fund() method)
        $user->wallet_balance = $fundingAmount;
        $user->save();

        // Verify database has correct balance
        $user->refresh();
        $this->assertEquals($fundingAmount, $user->wallet_balance, 'Database should have correct balance after funding');

        // Step 3: Simulate home screen requesting balance (like mobile app does)
        // This simulates the user returning to home screen after wallet funding
        $homeScreenResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance');

        $homeScreenResponse->assertStatus(200);
        $displayedBalance = $homeScreenResponse->json('data.balance');

        // EXPECTED BEHAVIOR (will fail on unfixed code due to stale cache)
        // The displayed balance should match the database balance
        $this->assertEquals(
            $fundingAmount,
            $displayedBalance,
            "Wallet balance should display ₦{$fundingAmount} after funding, not stale cached ₦0.0. " .
            "Database has ₦{$user->wallet_balance} but API returned ₦{$displayedBalance}. " .
            "This confirms the cache invalidation bug exists."
        );

        // Additional assertion: Balance should NOT be zero
        $this->assertGreaterThan(
            0.0,
            $displayedBalance,
            "Displayed balance should be greater than ₦0.0 after funding. " .
            "Found ₦{$displayedBalance} - this is the stale cache bug!"
        );
    }

    /**
     * Property: Multiple wallet operations should show correct final balance
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS showing stale cached balance
     * 
     * @test
     * @group property
     * @group bugfix
     * @group Feature: payments-banks-wallet-balance-fix, Property 1: Bug Condition - Wallet Balance Shows ₦0.0 Despite Non-Zero Database Balance
     */
    public function multiple_wallet_operations_should_show_correct_final_balance()
    {
        // Create user with initial balance
        $initialBalance = 500000.0;
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => $initialBalance
        ]);

        // Authenticate user
        $token = $user->createToken('test-token')->plainTextToken;

        // Step 1: Get initial balance (will be cached)
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance');

        $response1->assertStatus(200);
        $this->assertEquals($initialBalance, $response1->json('data.balance'));

        // Step 2: Perform multiple wallet operations
        $operations = [
            ['type' => 'fund', 'amount' => 250000.0],
            ['type' => 'fund', 'amount' => 100000.0],
            ['type' => 'withdraw', 'amount' => 50000.0],
        ];

        $expectedBalance = $initialBalance;
        foreach ($operations as $operation) {
            if ($operation['type'] === 'fund') {
                $expectedBalance += $operation['amount'];
            } else {
                $expectedBalance -= $operation['amount'];
            }
            
            // Update database directly (simulating wallet operation)
            $user->wallet_balance = $expectedBalance;
            $user->save();
        }

        // Verify database has correct final balance
        $user->refresh();
        $this->assertEquals($expectedBalance, $user->wallet_balance, 'Database should have correct final balance');

        // Step 3: Check balance after operations (simulating home screen refresh)
        $finalResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance');

        $finalResponse->assertStatus(200);
        $displayedBalance = $finalResponse->json('data.balance');

        // EXPECTED BEHAVIOR (will fail on unfixed code due to stale cache)
        $this->assertEquals(
            $expectedBalance,
            $displayedBalance,
            "After multiple operations, balance should be ₦{$expectedBalance}, not stale cached ₦{$displayedBalance}. " .
            "Database has ₦{$user->wallet_balance} but API returned ₦{$displayedBalance}. " .
            "This confirms cache is not invalidated after wallet operations."
        );
    }

    /**
     * Property: Force refresh parameter should bypass cache and return fresh balance
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS because force_refresh parameter is not implemented
     * 
     * @test
     * @group property
     * @group bugfix
     * @group Feature: payments-banks-wallet-balance-fix, Property 1: Bug Condition - Wallet Balance Shows ₦0.0 Despite Non-Zero Database Balance
     */
    public function force_refresh_should_bypass_cache_and_return_fresh_balance()
    {
        // Create user with initial balance
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => 0.0
        ]);

        // Authenticate user
        $token = $user->createToken('test-token')->plainTextToken;

        // Step 1: Get initial balance (will be cached as ₦0.0)
        $initialResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance');

        $initialResponse->assertStatus(200);
        $this->assertEquals(0.0, $initialResponse->json('data.balance'));

        // Step 2: Update database balance (simulating wallet funding)
        $newBalance = 750000.0;
        $user->wallet_balance = $newBalance;
        $user->save();

        // Step 3: Request balance WITH force_refresh parameter
        // This simulates mobile app requesting fresh data after wallet operation
        $forceRefreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance?force_refresh=1');

        $forceRefreshResponse->assertStatus(200);
        $displayedBalance = $forceRefreshResponse->json('data.balance');

        // EXPECTED BEHAVIOR (will fail on unfixed code because force_refresh is not implemented)
        $this->assertEquals(
            $newBalance,
            $displayedBalance,
            "With force_refresh=1, balance should be ₦{$newBalance} from database, not cached ₦{$displayedBalance}. " .
            "This confirms force_refresh parameter is not implemented."
        );
    }

    /**
     * Property: Cache should be invalidated immediately after wallet funding
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS because cache is not invalidated
     * 
     * @test
     * @group property
     * @group bugfix
     * @group Feature: payments-banks-wallet-balance-fix, Property 1: Bug Condition - Wallet Balance Shows ₦0.0 Despite Non-Zero Database Balance
     */
    public function cache_should_be_invalidated_after_wallet_funding()
    {
        // Create user with initial balance
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => 100000.0
        ]);

        // Authenticate user
        $token = $user->createToken('test-token')->plainTextToken;

        // Step 1: Get initial balance (will be cached)
        $initialResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance');

        $initialResponse->assertStatus(200);
        $cachedBalance = $initialResponse->json('data.balance');
        $this->assertEquals(100000.0, $cachedBalance);

        // Verify cache exists
        $cacheKey = "wallet_balance_{$user->id}";
        $this->assertTrue(Cache::has($cacheKey), 'Cache should exist after first balance request');

        // Step 2: Update database balance (simulating wallet funding)
        $newBalance = 500000.0;
        $user->wallet_balance = $newBalance;
        $user->save();

        // Step 3: Check if cache was invalidated
        // On UNFIXED code, cache will still exist with old value
        // On FIXED code, cache should be cleared
        
        // Get balance again (should fetch from database if cache was invalidated)
        $updatedResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance');

        $updatedResponse->assertStatus(200);
        $displayedBalance = $updatedResponse->json('data.balance');

        // EXPECTED BEHAVIOR (will fail on unfixed code)
        // After wallet operation, cache should be invalidated and fresh balance should be returned
        $this->assertEquals(
            $newBalance,
            $displayedBalance,
            "After wallet funding, cache should be invalidated and balance should be ₦{$newBalance}, not ₦{$displayedBalance}. " .
            "This confirms cache invalidation is not working."
        );
    }

    /**
     * Property: Rapid successive balance checks after funding should return correct balance
     * 
     * This tests the race condition scenario where home screen loads immediately after funding.
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS showing stale cached balance
     * 
     * @test
     * @group property
     * @group bugfix
     * @group Feature: payments-banks-wallet-balance-fix, Property 1: Bug Condition - Wallet Balance Shows ₦0.0 Despite Non-Zero Database Balance
     */
    public function rapid_balance_checks_after_funding_should_return_correct_balance()
    {
        // Create user with initial balance
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => 0.0
        ]);

        // Authenticate user
        $token = $user->createToken('test-token')->plainTextToken;

        // Step 1: Get initial balance (will be cached as ₦0.0)
        $initialResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/wallet/balance');

        $initialResponse->assertStatus(200);
        $this->assertEquals(0.0, $initialResponse->json('data.balance'));

        // Step 2: Fund wallet
        $fundingAmount = 1015000.0;
        $user->wallet_balance = $fundingAmount;
        $user->save();

        // Step 3: Make rapid successive balance requests (simulating home screen loading immediately)
        $requestCount = 5;
        for ($i = 0; $i < $requestCount; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/wallet/balance');

            $response->assertStatus(200);
            $displayedBalance = $response->json('data.balance');

            // EXPECTED BEHAVIOR (will fail on unfixed code)
            // All requests should return the correct balance, not stale cache
            $this->assertEquals(
                $fundingAmount,
                $displayedBalance,
                "Request #{$i}: Balance should be ₦{$fundingAmount} after funding, not ₦{$displayedBalance}. " .
                "This confirms the race condition bug where home screen loads before cache expires."
            );
        }
    }
}
