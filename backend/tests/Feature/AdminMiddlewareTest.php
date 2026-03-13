<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can access admin routes.
     */
    public function test_admin_can_access_admin_routes(): void
    {
        // Create and authenticate as admin
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        // Access admin route
        $response = $this->getJson('/api/v1/admin/dashboard/stats');

        // Assert successful access
        $response->assertStatus(200);
    }

    /**
     * Test regular user cannot access admin routes.
     */
    public function test_regular_user_cannot_access_admin_routes(): void
    {
        // Create and authenticate as regular user
        $user = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        // Attempt to access admin route
        $response = $this->getJson('/api/v1/admin/dashboard/stats');

        // Assert access denied
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Access denied. Admin privileges required.',
                ],
            ]);
    }

    /**
     * Test unauthenticated user cannot access admin routes.
     */
    public function test_unauthenticated_user_cannot_access_admin_routes(): void
    {
        // Attempt to access admin route without authentication
        $response = $this->getJson('/api/v1/admin/dashboard/stats');

        // Assert unauthorized
        $response->assertStatus(401);
    }
}
