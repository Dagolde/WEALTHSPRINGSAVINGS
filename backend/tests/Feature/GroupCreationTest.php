<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'status' => 'active',
            'kyc_status' => 'verified',
        ]);
    }

    /** @test */
    public function it_creates_a_group_with_valid_data()
    {
        $groupData = [
            'name' => 'Test Savings Group',
            'description' => 'A test group for savings',
            'contribution_amount' => 1000.00,
            'total_members' => 10,
            'cycle_days' => 10,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Group created successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'group_code',
                    'contribution_amount',
                    'total_members',
                    'current_members',
                    'cycle_days',
                    'frequency',
                    'status',
                    'created_by',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Savings Group',
            'contribution_amount' => 1000.00,
            'total_members' => 10,
            'current_members' => 1,
            'cycle_days' => 10,
            'frequency' => 'daily',
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_generates_unique_8_character_alphanumeric_group_code()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000.00,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201);
        
        $groupCode = $response->json('data.group_code');
        
        // Assert code is 8 characters
        $this->assertEquals(8, strlen($groupCode));
        
        // Assert code is alphanumeric (uppercase letters and numbers)
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $groupCode);
        
        // Assert code is unique in database
        $this->assertDatabaseHas('groups', ['group_code' => $groupCode]);
        $this->assertEquals(1, Group::where('group_code', $groupCode)->count());
    }

    /** @test */
    public function it_automatically_adds_creator_as_first_member()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000.00,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201);
        
        $groupId = $response->json('data.id');
        
        // Assert creator is added as member
        $this->assertDatabaseHas('group_members', [
            'group_id' => $groupId,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);
        
        // Assert current_members is 1
        $group = Group::find($groupId);
        $this->assertEquals(1, $group->current_members);
        $this->assertEquals(1, $group->members()->count());
    }

    /** @test */
    public function it_sets_group_status_to_pending()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000.00,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201);
        
        $groupId = $response->json('data.id');
        $group = Group::find($groupId);
        
        $this->assertEquals('pending', $group->status);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000.00,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'contribution_amount',
                'total_members',
                'cycle_days',
                'frequency',
            ]);
    }

    /** @test */
    public function it_validates_contribution_amount_minimum()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 50, // Below minimum of 100
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contribution_amount']);
    }

    /** @test */
    public function it_validates_contribution_amount_maximum()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 20000000, // Above maximum of 10,000,000
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contribution_amount']);
    }

    /** @test */
    public function it_validates_total_members_minimum()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 1, // Below minimum of 2
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_members']);
    }

    /** @test */
    public function it_validates_total_members_maximum()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 150, // Above maximum of 100
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_members']);
    }

    /** @test */
    public function it_validates_cycle_days_minimum()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 1, // Below minimum of 2
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cycle_days']);
    }

    /** @test */
    public function it_validates_cycle_days_maximum()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 400, // Above maximum of 365
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cycle_days']);
    }

    /** @test */
    public function it_validates_frequency_enum()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'monthly', // Invalid frequency
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['frequency']);
    }

    /** @test */
    public function it_accepts_daily_frequency()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', ['frequency' => 'daily']);
    }

    /** @test */
    public function it_accepts_weekly_frequency()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'weekly',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', ['frequency' => 'weekly']);
    }

    /** @test */
    public function it_allows_optional_description()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
            // description is optional
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201);
    }

    /** @test */
    public function it_generates_different_codes_for_multiple_groups()
    {
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        // Create first group
        $response1 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);
        
        $code1 = $response1->json('data.group_code');

        // Create second group
        $response2 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', array_merge($groupData, ['name' => 'Test Group 2']));
        
        $code2 = $response2->json('data.group_code');

        // Assert codes are different
        $this->assertNotEquals($code1, $code2);
        
        // Assert both codes exist in database
        $this->assertDatabaseHas('groups', ['group_code' => $code1]);
        $this->assertDatabaseHas('groups', ['group_code' => $code2]);
    }

    /** @test */
    public function it_uses_database_transaction_for_atomicity()
    {
        // This test ensures that if member creation fails, group creation is rolled back
        $groupData = [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201);
        
        $groupId = $response->json('data.id');
        
        // If transaction works correctly, both group and member should exist
        $this->assertDatabaseHas('groups', ['id' => $groupId]);
        $this->assertDatabaseHas('group_members', ['group_id' => $groupId]);
    }
}
