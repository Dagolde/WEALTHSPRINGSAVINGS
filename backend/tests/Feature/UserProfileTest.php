<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting user profile successfully.
     */
    public function test_get_profile_successfully(): void
    {
        // Create a user
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+2348012345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 1000.00,
            'status' => 'active',
        ]);

        // Authenticate and get profile
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '+2348012345678',
                    'kyc_status' => 'verified',
                    'wallet_balance' => '1000.00',
                    'status' => 'active',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'kyc_status',
                    'wallet_balance',
                    'status',
                    'created_at',
                ],
            ]);
    }

    /**
     * Test getting profile without authentication.
     */
    public function test_get_profile_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/user/profile');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    /**
     * Test updating profile successfully.
     */
    public function test_update_profile_successfully(): void
    {
        // Create a user
        $user = User::factory()->create([
            'name' => 'John Doe',
            'phone' => '+2348012345678',
        ]);

        // Update profile
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'name' => 'John Updated',
                'phone' => '+2348012345679',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => 'John Updated',
                    'phone' => '+2348012345679',
                ],
            ]);

        // Verify database was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Updated',
            'phone' => '+2348012345679',
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile_updated',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);

        // Verify audit log contains old and new values
        $auditLog = AuditLog::where('user_id', $user->id)
            ->where('action', 'profile_updated')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('John Doe', $auditLog->old_values['name']);
        $this->assertEquals('+2348012345678', $auditLog->old_values['phone']);
        $this->assertEquals('John Updated', $auditLog->new_values['name']);
        $this->assertEquals('+2348012345679', $auditLog->new_values['phone']);
    }

    /**
     * Test updating profile with invalid data.
     */
    public function test_update_profile_with_invalid_data(): void
    {
        $user = User::factory()->create();

        // Test with missing name
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'phone' => '+2348012345679',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonValidationErrors(['name']);

        // Test with missing phone
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'name' => 'John Updated',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        // Test with name too long
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'name' => str_repeat('a', 256),
                'phone' => '+2348012345679',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Test with phone too long
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'name' => 'John Updated',
                'phone' => str_repeat('1', 21),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * Test updating profile with duplicate phone number.
     */
    public function test_update_profile_with_duplicate_phone(): void
    {
        // Create two users
        $user1 = User::factory()->create([
            'phone' => '+2348012345678',
        ]);

        $user2 = User::factory()->create([
            'phone' => '+2348012345679',
        ]);

        // Try to update user2's phone to user1's phone
        $response = $this->actingAs($user2, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'name' => 'John Updated',
                'phone' => '+2348012345678',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonValidationErrors(['phone']);

        // Verify database was not updated
        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'phone' => '+2348012345679',
        ]);
    }

    /**
     * Test updating profile with same phone number (should succeed).
     */
    public function test_update_profile_with_same_phone(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'phone' => '+2348012345678',
        ]);

        // Update name but keep same phone
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'name' => 'John Updated',
                'phone' => '+2348012345678',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'name' => 'John Updated',
                    'phone' => '+2348012345678',
                ],
            ]);

        // Verify database was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Updated',
            'phone' => '+2348012345678',
        ]);
    }

    /**
     * Test that email cannot be updated through profile endpoint.
     */
    public function test_email_cannot_be_updated(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);

        // Try to update email (should be ignored)
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'name' => 'John Updated',
                'phone' => '+2348012345679',
                'email' => 'newemail@example.com',
            ]);

        $response->assertStatus(200);

        // Verify email was not changed
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'email' => 'newemail@example.com',
        ]);
    }

    /**
     * Test audit log captures IP address and user agent.
     */
    public function test_audit_log_captures_request_metadata(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'phone' => '+2348012345678',
        ]);

        // Update profile with custom headers
        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders([
                'User-Agent' => 'TestBrowser/1.0',
            ])
            ->putJson('/api/v1/user/profile', [
                'name' => 'John Updated',
                'phone' => '+2348012345679',
            ]);

        $response->assertStatus(200);

        // Verify audit log captured metadata
        $auditLog = AuditLog::where('user_id', $user->id)
            ->where('action', 'profile_updated')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertNotNull($auditLog->ip_address);
        $this->assertEquals('TestBrowser/1.0', $auditLog->user_agent);
    }

    /**
     * Test profile update without authentication.
     */
    public function test_update_profile_unauthenticated(): void
    {
        $response = $this->putJson('/api/v1/user/profile', [
            'name' => 'John Updated',
            'phone' => '+2348012345679',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    /**
     * Test wallet balance is formatted correctly in profile response.
     */
    public function test_wallet_balance_formatting(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 1234.56,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user/profile');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'wallet_balance' => '1234.56',
                ],
            ]);

        // Test with zero balance
        $user->update(['wallet_balance' => 0]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user/profile');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'wallet_balance' => '0.00',
                ],
            ]);
    }

    /**
     * Test suspended user cannot access profile endpoints.
     */
    public function test_suspended_user_cannot_access_profile(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended',
        ]);

        // Try to get profile
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user/profile');

        $response->assertStatus(403);

        // Try to update profile
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/user/profile', [
                'name' => 'John Updated',
                'phone' => '+2348012345679',
            ]);

        $response->assertStatus(403);
    }
}
