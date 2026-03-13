<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can login with valid credentials.
     */
    public function test_admin_can_login_with_valid_credentials(): void
    {
        // Create an admin user
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Attempt to login
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        // Assert successful login
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                    ],
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Admin login successful',
                'data' => [
                    'user' => [
                        'email' => 'admin@test.com',
                        'role' => 'admin',
                    ],
                ],
            ]);

        // Assert token is returned
        $this->assertNotEmpty($response->json('data.token'));
    }

    /**
     * Test regular user cannot login as admin.
     */
    public function test_regular_user_cannot_login_as_admin(): void
    {
        // Create a regular user
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 'active',
        ]);

        // Attempt to login as admin
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'user@test.com',
            'password' => 'password123',
        ]);

        // Assert access denied
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.',
            ]);
    }

    /**
     * Test admin cannot login with invalid credentials.
     */
    public function test_admin_cannot_login_with_invalid_credentials(): void
    {
        // Create an admin user
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Attempt to login with wrong password
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        // Assert unauthorized
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    /**
     * Test inactive admin cannot login.
     */
    public function test_inactive_admin_cannot_login(): void
    {
        // Create an inactive admin user
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'inactive',
        ]);

        // Attempt to login
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        // Assert access denied
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account is not active. Please contact support.',
            ]);
    }

    /**
     * Test admin login requires email and password.
     */
    public function test_admin_login_requires_email_and_password(): void
    {
        // Attempt to login without credentials
        $response = $this->postJson('/api/v1/auth/admin/login', []);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Test admin login with invalid email format.
     */
    public function test_admin_login_with_invalid_email_format(): void
    {
        // Attempt to login with invalid email
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
