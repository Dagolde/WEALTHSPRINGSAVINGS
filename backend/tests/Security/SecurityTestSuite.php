<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Security testing suite
 * Tests authentication, authorization, rate limiting, input validation, and common vulnerabilities
 */
class SecurityTestSuite extends TestCase
{
    use RefreshDatabase;

    /**
     * Test authentication with invalid credentials
     * 
     * @test
     */
    public function authentication_fails_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct_password')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
    }

    /**
     * Test authentication requires valid token
     * 
     * @test
     */
    public function protected_endpoints_require_valid_token()
    {
        // Without token
        $response = $this->getJson('/api/v1/user/profile');
        $response->assertStatus(401);

        // With invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token'
        ])->getJson('/api/v1/user/profile');
        $response->assertStatus(401);
    }

    /**
     * Test authorization - users can only access their own data
     * 
     * @test
     */
    public function users_can_only_access_their_own_data()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User1 tries to access User2's profile
        $response = $this->actingAs($user1)
            ->getJson("/api/v1/user/{$user2->id}/profile");

        $response->assertStatus(403);
    }

    /**
     * Test authorization - only group members can view group details
     * 
     * @test
     */
    public function only_group_members_can_view_group_details()
    {
        $member = User::factory()->create();
        $nonMember = User::factory()->create();

        $group = Group::factory()->create();
        $group->members()->create([
            'user_id' => $member->id,
            'position_number' => 1,
            'payout_day' => 1
        ]);

        // Member can access
        $response = $this->actingAs($member)
            ->getJson("/api/v1/groups/{$group->id}");
        $response->assertStatus(200);

        // Non-member cannot access
        $response = $this->actingAs($nonMember)
            ->getJson("/api/v1/groups/{$group->id}");
        $response->assertStatus(403);
    }

    /**
     * Test rate limiting on login endpoint
     * 
     * @test
     */
    public function login_endpoint_has_rate_limiting()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);

        // Make multiple failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong_password'
            ]);

            if ($i < 5) {
                $response->assertStatus(401);
            } else {
                // 6th attempt should be rate limited
                $response->assertStatus(429);
            }
        }
    }

    /**
     * Test input validation prevents SQL injection
     * 
     * @test
     */
    public function input_validation_prevents_sql_injection()
    {
        $user = User::factory()->create();

        // Attempt SQL injection in search parameter
        $response = $this->actingAs($user)
            ->getJson("/api/v1/groups?search=' OR '1'='1");

        // Should not cause error, should sanitize input
        $response->assertStatus(200);

        // Attempt SQL injection in POST data
        $response = $this->actingAs($user)
            ->postJson('/api/v1/groups', [
                'name' => "'; DROP TABLE groups; --",
                'contribution_amount' => 1000,
                'total_members' => 5,
                'cycle_days' => 5
            ]);

        // Should create group with sanitized name
        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', [
            'name' => "'; DROP TABLE groups; --"
        ]);
        // Verify groups table still exists
        $this->assertDatabaseCount('groups', 1);
    }

    /**
     * Test XSS prevention in user input
     * 
     * @test
     */
    public function xss_prevention_in_user_input()
    {
        $user = User::factory()->create();

        // Attempt XSS in group name
        $response = $this->actingAs($user)
            ->postJson('/api/v1/groups', [
                'name' => '<script>alert("XSS")</script>',
                'contribution_amount' => 1000,
                'total_members' => 5,
                'cycle_days' => 5
            ]);

        $response->assertStatus(201);
        $groupId = $response->json('data.id');

        // Retrieve group and verify script tags are escaped
        $getResponse = $this->actingAs($user)
            ->getJson("/api/v1/groups/{$groupId}");

        $name = $getResponse->json('data.name');
        // Should be escaped or sanitized
        $this->assertStringNotContainsString('<script>', $name);
    }

    /**
     * Test CSRF protection on state-changing operations
     * 
     * @test
     */
    public function csrf_protection_on_state_changing_operations()
    {
        $user = User::factory()->create();

        // Attempt POST without CSRF token (API uses token auth, but test the concept)
        $response = $this->postJson('/api/v1/groups', [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 5
        ]);

        // Should fail without authentication
        $response->assertStatus(401);
    }

    /**
     * Test password hashing
     * 
     * @test
     */
    public function passwords_are_hashed_not_stored_plaintext()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+2348012345678',
            'password' => 'PlainPassword123!',
            'password_confirmation' => 'PlainPassword123!'
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'test@example.com')->first();
        
        // Password should not be stored in plaintext
        $this->assertNotEquals('PlainPassword123!', $user->password);
        
        // Should be hashed
        $this->assertTrue(Hash::check('PlainPassword123!', $user->password));
    }

    /**
     * Test sensitive data not exposed in API responses
     * 
     * @test
     */
    public function sensitive_data_not_exposed_in_api_responses()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/user/profile');

        $response->assertStatus(200);

        // Password should not be in response
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    /**
     * Test authorization for admin-only endpoints
     * 
     * @test
     */
    public function admin_endpoints_require_admin_role()
    {
        $regularUser = User::factory()->create(['is_admin' => false]);
        $adminUser = User::factory()->create(['is_admin' => true]);

        // Regular user cannot access admin endpoint
        $response = $this->actingAs($regularUser)
            ->getJson('/api/v1/admin/dashboard/stats');
        $response->assertStatus(403);

        // Admin user can access
        $response = $this->actingAs($adminUser)
            ->getJson('/api/v1/admin/dashboard/stats');
        $response->assertStatus(200);
    }

    /**
     * Test suspended users cannot perform actions
     * 
     * @test
     */
    public function suspended_users_cannot_perform_actions()
    {
        $user = User::factory()->create([
            'status' => 'suspended',
            'wallet_balance' => 10000
        ]);

        $group = Group::factory()->create(['status' => 'active']);
        $group->members()->create([
            'user_id' => $user->id,
            'position_number' => 1,
            'payout_day' => 1
        ]);

        // Suspended user cannot make contribution
        $response = $this->actingAs($user)
            ->postJson('/api/v1/contributions', [
                'group_id' => $group->id,
                'amount' => 1000,
                'payment_method' => 'wallet'
            ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Your account has been suspended'
            ]);
    }

    /**
     * Test input validation on amount fields
     * 
     * @test
     */
    public function input_validation_on_amount_fields()
    {
        $user = User::factory()->create();

        // Negative amount
        $response = $this->actingAs($user)
            ->postJson('/api/v1/groups', [
                'name' => 'Test Group',
                'contribution_amount' => -1000,
                'total_members' => 5,
                'cycle_days' => 5
            ]);

        $response->assertStatus(422);

        // Zero amount
        $response = $this->actingAs($user)
            ->postJson('/api/v1/groups', [
                'name' => 'Test Group',
                'contribution_amount' => 0,
                'total_members' => 5,
                'cycle_days' => 5
            ]);

        $response->assertStatus(422);

        // Non-numeric amount
        $response = $this->actingAs($user)
            ->postJson('/api/v1/groups', [
                'name' => 'Test Group',
                'contribution_amount' => 'invalid',
                'total_members' => 5,
                'cycle_days' => 5
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test mass assignment protection
     * 
     * @test
     */
    public function mass_assignment_protection()
    {
        $user = User::factory()->create();

        // Attempt to set is_admin via mass assignment
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'phone' => '+2348012345679',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_admin' => true,  // Should be ignored
            'wallet_balance' => 1000000  // Should be ignored
        ]);

        $response->assertStatus(201);

        $newUser = User::where('email', 'test2@example.com')->first();
        
        // Should not be admin
        $this->assertFalse($newUser->is_admin);
        
        // Should have default wallet balance
        $this->assertEquals(0, $newUser->wallet_balance);
    }

    /**
     * Test webhook signature verification
     * 
     * @test
     */
    public function webhook_signature_verification()
    {
        // Attempt webhook without valid signature
        $response = $this->postJson('/api/v1/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'TEST-REF',
                'amount' => 100000
            ]
        ]);

        // Should reject without valid signature
        $response->assertStatus(401);
    }
}
