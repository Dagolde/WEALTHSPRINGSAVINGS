<?php

namespace Tests\Property;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Bug Condition Exploration Test for Missing Banks Endpoint
 * 
 * **Validates: Requirements 2.1**
 * 
 * **Property 1: Bug Condition - Banks Endpoint Returns 404**
 * 
 * CRITICAL: This test MUST FAIL on unfixed code - failure confirms the bug exists.
 * DO NOT attempt to fix the test or the code when it fails.
 * 
 * This test encodes the EXPECTED behavior (200 response with banks list).
 * It will validate the fix when it passes after implementation.
 * 
 * GOAL: Surface counterexamples that demonstrate the missing endpoint bug.
 * 
 * For any authenticated request to GET /api/v1/payments/banks,
 * the system SHOULD return 200 status with a JSON array of Nigerian banks.
 * 
 * On UNFIXED code, this test will FAIL with 404 error, proving the endpoint is missing.
 */
class BanksEndpointBugConditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property: Banks endpoint should return 200 with banks list for authenticated users
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS with 404 error
     * This failure confirms the bug exists and the endpoint is missing.
     * 
     * @test
     * @group property
     * @group bugfix
     * @group Feature: payments-banks-wallet-balance-fix, Property 1: Bug Condition - Banks Endpoint Returns 404
     */
    public function banks_endpoint_should_return_banks_list_for_authenticated_users()
    {
        // Create authenticated user
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified'
        ]);

        // Authenticate user and get token
        $token = $user->createToken('test-token')->plainTextToken;

        // Make authenticated request to banks endpoint
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/payments/banks');

        // EXPECTED BEHAVIOR (will fail on unfixed code with 404)
        // This assertion encodes what SHOULD happen after the fix
        $response->assertStatus(200);
        
        // Verify response structure
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'code',
                    'name'
                ]
            ]
        ]);

        // Verify data is an array
        $data = $response->json('data');
        $this->assertIsArray($data, 'Banks data should be an array');
        
        // Verify array is not empty
        $this->assertNotEmpty($data, 'Banks list should not be empty');
        
        // Verify each bank has required fields
        foreach ($data as $bank) {
            $this->assertArrayHasKey('code', $bank, 'Each bank should have a code');
            $this->assertArrayHasKey('name', $bank, 'Each bank should have a name');
            $this->assertNotEmpty($bank['code'], 'Bank code should not be empty');
            $this->assertNotEmpty($bank['name'], 'Bank name should not be empty');
        }
    }

    /**
     * Property: Banks endpoint should require authentication
     * 
     * This test verifies that unauthenticated requests are properly rejected.
     * This should pass even on unfixed code (authentication middleware works).
     * 
     * @test
     * @group property
     * @group bugfix
     * @group Feature: payments-banks-wallet-balance-fix, Property 1: Bug Condition - Banks Endpoint Returns 404
     */
    public function banks_endpoint_should_require_authentication()
    {
        // Make unauthenticated request to banks endpoint
        $response = $this->getJson('/api/v1/payments/banks');

        // Should return 401 Unauthorized (or 404 on unfixed code)
        // On unfixed code, this will return 404 because route doesn't exist
        // After fix, this should return 401 because authentication is required
        $this->assertContains(
            $response->status(),
            [401, 404],
            'Unauthenticated request should return 401 (after fix) or 404 (before fix)'
        );
    }

    /**
     * Property: Banks endpoint should return consistent data across multiple requests
     * 
     * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS with 404 error
     * 
     * @test
     * @group property
     * @group bugfix
     * @group Feature: payments-banks-wallet-balance-fix, Property 1: Bug Condition - Banks Endpoint Returns 404
     */
    public function banks_endpoint_should_return_consistent_data_across_requests()
    {
        // Create authenticated user
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'kyc_status' => 'verified'
        ]);

        // Authenticate user and get token
        $token = $user->createToken('test-token')->plainTextToken;

        $responses = [];
        $requestCount = 3;
        
        // Make multiple requests
        for ($i = 0; $i < $requestCount; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/payments/banks');

            // EXPECTED BEHAVIOR (will fail on unfixed code with 404)
            $response->assertStatus(200);
            
            $responses[] = $response->json('data');
        }

        // Verify all responses are identical
        $firstResponse = $responses[0];
        foreach ($responses as $response) {
            $this->assertEquals(
                $firstResponse,
                $response,
                'Banks endpoint should return consistent data across multiple requests'
            );
        }
    }
}
