<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/**
 * Integration tests for Payments Banks and Wallet Balance bugfix
 * 
 * Tests the following scenarios:
 * 1. Full flow: Login → Fund wallet → Return to home screen → Verify correct balance
 * 2. Full flow: Login → Open bank linking → Verify banks list loads → Select bank
 * 3. Multiple rapid wallet operations show correct final balance
 * 4. Wallet balance updates across multiple screens
 * 5. No regressions in other features (KYC, groups, contributions)
 */
class PaymentsBanksWalletBalanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Scenario 1: Full flow - Login → Fund wallet → Return to home screen → Verify correct balance
     * 
     * This test verifies that Bug 2 (wallet balance showing ₦0.0) is fixed.
     * After wallet funding, the home screen should display the correct balance.
     * 
     * @test
     */
    public function full_flow_login_fund_wallet_verify_balance_on_home_screen()
    {
        // Step 1: Create user and login
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'kyc_status' => 'verified',
            'wallet_balance' => 0
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // Step 2: Check initial balance (should be 0)
        $initialBalanceResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance');

        $initialBalanceResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['balance' => 0]
            ]);

        // Step 3: Fund wallet
        $fundingData = [
            'amount' => 10000,
            'payment_method' => 'card'
        ];

        $fundingResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/wallet/fund', $fundingData);

        $fundingResponse->assertStatus(200);
        $fundingRef = $fundingResponse->json('data.reference');

        // Step 4: Simulate successful payment webhook
        $webhookData = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $fundingRef,
                'amount' => 1000000, // 10000 in kobo
                'status' => 'success'
            ]
        ];

        $this->postJson('/api/v1/webhooks/paystack', $webhookData);

        // Step 5: Return to home screen - check balance (simulating user navigation)
        // This should show the correct balance, not ₦0.0
        $homeBalanceResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance');

        $homeBalanceResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['balance' => 10000]
            ]);

        // Step 6: Verify database has correct balance
        $user->refresh();
        $this->assertEquals(10000, $user->wallet_balance);

        // Step 7: Test force refresh parameter
        $forceRefreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance?force_refresh=1');

        $forceRefreshResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['balance' => 10000]
            ]);
    }

    /**
     * Test Scenario 2: Full flow - Login → Open bank linking → Verify banks list loads → Select bank
     * 
     * This test verifies that Bug 1 (missing /payments/banks endpoint) is fixed.
     * The banks list should load successfully with Nigerian banks.
     * 
     * @test
     */
    public function full_flow_login_bank_linking_verify_banks_list_loads()
    {
        // Step 1: Create user and login
        $user = User::factory()->create([
            'email' => 'banktest@example.com',
            'password' => bcrypt('password123'),
            'kyc_status' => 'verified'
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'banktest@example.com',
            'password' => 'password123'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // Step 2: Open bank linking screen - fetch banks list
        $banksResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/payments/banks');

        // Step 3: Verify banks list loads successfully (not 404)
        $banksResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['code', 'name']
                ]
            ]);

        $banks = $banksResponse->json('data');
        $this->assertNotEmpty($banks);
        $this->assertIsArray($banks);

        // Step 4: Verify Nigerian banks are present
        $bankCodes = array_column($banks, 'code');
        $this->assertContains('058', $bankCodes); // GTBank
        $this->assertContains('044', $bankCodes); // Access Bank

        // Step 5: Verify we can select a bank (banks list is available)
        $selectedBank = $banks[0];
        
        // Verify bank has required fields
        $this->assertArrayHasKey('code', $selectedBank);
        $this->assertArrayHasKey('name', $selectedBank);
        $this->assertNotEmpty($selectedBank['code']);
        $this->assertNotEmpty($selectedBank['name']);
    }

    /**
     * Test Scenario 3: Multiple rapid wallet operations show correct final balance
     * 
     * This test verifies that cache invalidation works correctly for rapid operations.
     * 
     * @test
     */
    public function multiple_rapid_wallet_operations_show_correct_final_balance()
    {
        // Step 1: Create user
        $user = User::factory()->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 5000
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Step 2: Perform multiple rapid wallet operations
        $walletService = app(\App\Services\WalletService::class);

        // Operation 1: Credit 1000
        $walletService->creditWallet($user, 1000, 'Test credit 1');
        
        // Check balance immediately
        $balance1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance?force_refresh=1');
        
        $balance1->assertJson(['data' => ['balance' => 6000]]);

        // Operation 2: Debit 500
        $walletService->debitWallet($user, 500, 'Test debit 1');
        
        // Check balance immediately
        $balance2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance?force_refresh=1');
        
        $balance2->assertJson(['data' => ['balance' => 5500]]);

        // Operation 3: Credit 2000
        $walletService->creditWallet($user, 2000, 'Test credit 2');
        
        // Check balance immediately
        $balance3 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance?force_refresh=1');
        
        $balance3->assertJson(['data' => ['balance' => 7500]]);

        // Operation 4: Debit 1500
        $walletService->debitWallet($user, 1500, 'Test debit 2');
        
        // Check final balance
        $finalBalance = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance?force_refresh=1');
        
        $finalBalance->assertJson(['data' => ['balance' => 6000]]);

        // Verify database matches
        $user->refresh();
        $this->assertEquals(6000, $user->wallet_balance);
    }

    /**
     * Test Scenario 4: Wallet balance updates across multiple screens
     * 
     * This test verifies that wallet balance is consistent when fetched through the wallet balance endpoint.
     * 
     * @test
     */
    public function wallet_balance_updates_across_multiple_screens()
    {
        // Step 1: Create user
        $user = User::factory()->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 10000
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Step 2: Check balance on wallet screen
        $walletBalance = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance');

        $walletBalance->assertJson(['data' => ['balance' => 10000]]);

        // Step 3: Perform wallet operation via controller (which updates database)
        $withdrawalData = [
            'amount' => 3000,
            'bank_account_id' => BankAccount::factory()->create([
                'user_id' => $user->id,
                'is_verified' => true,
                'is_primary' => true
            ])->id
        ];

        $withdrawalResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/wallet/withdraw', $withdrawalData);

        $withdrawalResponse->assertStatus(201);

        // Step 4: Check balance on wallet screen again (should show updated balance)
        $walletBalance2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance?force_refresh=1');

        $walletBalance2->assertJson(['data' => ['balance' => 7000]]);

        // Verify database balance matches
        $user->refresh();
        $this->assertEquals(7000, $user->wallet_balance);
    }

    /**
     * Test Scenario 5: No regressions in KYC feature
     * 
     * @test
     */
    public function no_regressions_in_kyc_feature()
    {
        // Create user
        $user = User::factory()->create([
            'kyc_status' => 'pending'
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Check KYC status
        $statusResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/user/kyc/status');

        $statusResponse->assertStatus(200)
            ->assertJsonFragment(['kyc_status' => 'pending']);
    }

    /**
     * Test Scenario 5: No regressions in groups feature
     * 
     * @test
     */
    public function no_regressions_in_groups_feature()
    {
        // Create user
        $user = User::factory()->create([
            'kyc_status' => 'verified'
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Create group
        $groupData = [
            'name' => 'Test Group',
            'description' => 'Test Description',
            'contribution_amount' => 5000,
            'total_members' => 3,
            'cycle_days' => 3,
            'frequency' => 'daily'
        ];

        $groupResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/groups', $groupData);

        $groupResponse->assertStatus(201);

        // List groups
        $listResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/groups');

        $listResponse->assertStatus(200);
        
        // Verify response has data array
        $this->assertArrayHasKey('data', $listResponse->json());
        $groups = $listResponse->json('data');
        $this->assertIsArray($groups);
        
        // Verify at least one group exists (the one we just created)
        $this->assertGreaterThanOrEqual(1, count($groups));
    }

    /**
     * Test Scenario 5: No regressions in contributions feature
     * 
     * @test
     */
    public function no_regressions_in_contributions_feature()
    {
        // Setup: Create active group with member
        $user = User::factory()->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 10000
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $group = Group::factory()->create([
            'status' => 'active',
            'contribution_amount' => 5000,
            'start_date' => now()->toDateString()
        ]);

        $group->members()->create([
            'user_id' => $user->id,
            'position_number' => 1,
            'payout_day' => 1
        ]);

        // Make contribution
        $contributionData = [
            'group_id' => $group->id,
            'amount' => 5000,
            'payment_method' => 'wallet'
        ];

        $contributionResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/contributions', $contributionData);

        $contributionResponse->assertStatus(201);

        // Verify wallet was debited
        $user->refresh();
        $this->assertEquals(5000, $user->wallet_balance);
    }

    /**
     * Test cache invalidation after wallet funding via controller
     * 
     * @test
     */
    public function cache_is_invalidated_after_wallet_funding()
    {
        // Create user
        $user = User::factory()->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 0
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Get balance (this will cache it)
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance');

        // Verify cache exists
        $cacheKey = "wallet_balance_{$user->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Fund wallet via controller (which should invalidate cache)
        $fundingData = [
            'amount' => 5000,
            'payment_method' => 'card'
        ];

        $fundingResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/wallet/fund', $fundingData);

        $fundingResponse->assertStatus(200);

        // Verify cache was invalidated
        $this->assertFalse(Cache::has($cacheKey));

        // Get balance again (should fetch fresh data)
        $balanceResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance');

        $balanceResponse->assertJson(['data' => ['balance' => 5000]]);
    }

    /**
     * Test cache invalidation after wallet withdrawal via controller
     * 
     * @test
     */
    public function cache_is_invalidated_after_wallet_withdrawal()
    {
        // Create user with bank account
        $user = User::factory()->create([
            'kyc_status' => 'verified',
            'wallet_balance' => 10000
        ]);

        $bankAccount = BankAccount::factory()->create([
            'user_id' => $user->id,
            'is_verified' => true,
            'is_primary' => true
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Get balance (this will cache it)
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance');

        // Verify cache exists
        $cacheKey = "wallet_balance_{$user->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Withdraw from wallet via controller (which should invalidate cache)
        $withdrawalData = [
            'amount' => 3000,
            'bank_account_id' => $bankAccount->id
        ];

        $withdrawalResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/wallet/withdraw', $withdrawalData);

        $withdrawalResponse->assertStatus(201);

        // Verify cache was invalidated
        $this->assertFalse(Cache::has($cacheKey));

        // Get balance again (should fetch fresh data)
        $balanceResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/wallet/balance');

        $balanceResponse->assertJson(['data' => ['balance' => 7000]]);
    }
}
