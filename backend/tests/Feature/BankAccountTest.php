<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_add_bank_account()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => 'John Doe',
                'account_number' => '0123456789',
                'bank_name' => 'First Bank',
                'bank_code' => '011',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bank account added successfully',
                'data' => [
                    'account_name' => 'John Doe',
                    'account_number' => '0123456789',
                    'bank_name' => 'First Bank',
                    'bank_code' => '011',
                    'is_verified' => false,
                    'is_primary' => true, // First account should be primary
                ],
            ]);

        // Verify bank account was created in database
        $this->assertDatabaseHas('bank_accounts', [
            'user_id' => $user->id,
            'account_number' => '0123456789',
            'bank_code' => '011',
            'is_primary' => true,
        ]);
    }

    /** @test */
    public function first_bank_account_is_set_as_primary()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => 'John Doe',
                'account_number' => '0123456789',
                'bank_name' => 'First Bank',
                'bank_code' => '011',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_primary' => true,
                ],
            ]);
    }

    /** @test */
    public function second_bank_account_is_not_set_as_primary()
    {
        $user = User::factory()->create();

        // Add first bank account
        BankAccount::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        // Add second bank account
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => 'Jane Doe',
                'account_number' => '9876543210',
                'bank_name' => 'Access Bank',
                'bank_code' => '044',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_primary' => false,
                ],
            ]);
    }

    /** @test */
    public function user_cannot_add_duplicate_bank_account()
    {
        $user = User::factory()->create();

        // Add first bank account
        BankAccount::factory()->create([
            'user_id' => $user->id,
            'account_number' => '0123456789',
            'bank_code' => '011',
        ]);

        // Try to add the same account again
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => 'John Doe',
                'account_number' => '0123456789',
                'bank_name' => 'First Bank',
                'bank_code' => '011',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This bank account is already linked to your profile',
            ]);
    }

    /** @test */
    public function user_can_add_same_account_number_with_different_bank_code()
    {
        $user = User::factory()->create();

        // Add first bank account
        BankAccount::factory()->create([
            'user_id' => $user->id,
            'account_number' => '0123456789',
            'bank_code' => '011',
        ]);

        // Add same account number but different bank
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => 'John Doe',
                'account_number' => '0123456789',
                'bank_name' => 'Access Bank',
                'bank_code' => '044',
            ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function add_bank_account_requires_authentication()
    {
        $response = $this->postJson('/api/v1/user/bank-account', [
            'account_name' => 'John Doe',
            'account_number' => '0123456789',
            'bank_name' => 'First Bank',
            'bank_code' => '011',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function add_bank_account_validates_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_name', 'account_number', 'bank_name', 'bank_code']);
    }

    /** @test */
    public function add_bank_account_validates_account_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => '',
                'account_number' => '0123456789',
                'bank_name' => 'First Bank',
                'bank_code' => '011',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_name']);
    }

    /** @test */
    public function add_bank_account_validates_account_number()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => 'John Doe',
                'account_number' => '',
                'bank_name' => 'First Bank',
                'bank_code' => '011',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_number']);
    }

    /** @test */
    public function add_bank_account_validates_bank_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => 'John Doe',
                'account_number' => '0123456789',
                'bank_name' => '',
                'bank_code' => '011',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank_name']);
    }

    /** @test */
    public function add_bank_account_validates_bank_code()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/bank-account', [
                'account_name' => 'John Doe',
                'account_number' => '0123456789',
                'bank_name' => 'First Bank',
                'bank_code' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank_code']);
    }

    /** @test */
    public function user_can_get_all_bank_accounts()
    {
        $user = User::factory()->create();

        // Create multiple bank accounts
        $account1 = BankAccount::factory()->create([
            'user_id' => $user->id,
            'account_name' => 'John Doe',
            'account_number' => '0123456789',
            'bank_name' => 'First Bank',
            'bank_code' => '011',
            'is_primary' => true,
        ]);

        $account2 = BankAccount::factory()->create([
            'user_id' => $user->id,
            'account_name' => 'Jane Doe',
            'account_number' => '9876543210',
            'bank_name' => 'Access Bank',
            'bank_code' => '044',
            'is_primary' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user/bank-accounts');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bank accounts retrieved successfully',
                'data' => [
                    [
                        'id' => $account1->id,
                        'account_name' => 'John Doe',
                        'account_number' => '0123456789',
                        'bank_name' => 'First Bank',
                        'bank_code' => '011',
                        'is_verified' => false,
                        'is_primary' => true,
                    ],
                    [
                        'id' => $account2->id,
                        'account_name' => 'Jane Doe',
                        'account_number' => '9876543210',
                        'bank_name' => 'Access Bank',
                        'bank_code' => '044',
                        'is_verified' => false,
                        'is_primary' => false,
                    ],
                ],
            ]);
    }

    /** @test */
    public function user_gets_empty_array_when_no_bank_accounts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user/bank-accounts');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bank accounts retrieved successfully',
                'data' => [],
            ]);
    }

    /** @test */
    public function user_only_sees_their_own_bank_accounts()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create bank account for user1
        BankAccount::factory()->create([
            'user_id' => $user1->id,
            'account_number' => '0123456789',
        ]);

        // Create bank account for user2
        BankAccount::factory()->create([
            'user_id' => $user2->id,
            'account_number' => '9876543210',
        ]);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/v1/user/bank-accounts');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('0123456789', $data[0]['account_number']);
    }

    /** @test */
    public function get_bank_accounts_requires_authentication()
    {
        $response = $this->getJson('/api/v1/user/bank-accounts');

        $response->assertStatus(401);
    }
}
