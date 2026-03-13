<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function group_code_is_always_8_characters()
    {
        $user = User::factory()->create();
        
        // Create multiple groups and verify all codes are 8 characters
        for ($i = 0; $i < 10; $i++) {
            $group = Group::factory()->create(['created_by' => $user->id]);
            $this->assertEquals(8, strlen($group->group_code));
        }
    }

    /** @test */
    public function group_code_is_alphanumeric_uppercase()
    {
        $user = User::factory()->create();
        
        // Create multiple groups and verify all codes are alphanumeric uppercase
        for ($i = 0; $i < 10; $i++) {
            $group = Group::factory()->create(['created_by' => $user->id]);
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $group->group_code);
        }
    }

    /** @test */
    public function group_codes_are_unique()
    {
        $user = User::factory()->create();
        
        // Create multiple groups
        $codes = [];
        for ($i = 0; $i < 20; $i++) {
            $group = Group::factory()->create(['created_by' => $user->id]);
            $codes[] = $group->group_code;
        }
        
        // Verify all codes are unique
        $uniqueCodes = array_unique($codes);
        $this->assertCount(20, $uniqueCodes);
    }

    /** @test */
    public function group_code_is_stored_in_database()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $user->id]);
        
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'group_code' => $group->group_code,
        ]);
    }

    /** @test */
    public function group_code_uniqueness_is_enforced_by_database()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $user->id]);
        
        // Attempt to create another group with the same code should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Group::create([
            'name' => 'Test Group',
            'group_code' => $group->group_code, // Duplicate code
            'contribution_amount' => 1000,
            'total_members' => 5,
            'cycle_days' => 5,
            'frequency' => 'daily',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
    }
}
