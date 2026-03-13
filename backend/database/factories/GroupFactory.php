<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalMembers = fake()->numberBetween(5, 20);
        
        return [
            'name' => fake()->words(3, true) . ' Ajo Group',
            'description' => fake()->sentence(),
            'group_code' => $this->generateGroupCode(),
            'contribution_amount' => fake()->randomElement([1000, 2000, 5000, 10000]),
            'total_members' => $totalMembers,
            'current_members' => 0,
            'cycle_days' => $totalMembers,
            'frequency' => 'daily',
            'status' => 'pending',
            'created_by' => User::factory(),
        ];
    }

    /**
     * Generate a unique 8-character alphanumeric group code.
     *
     * @return string
     */
    private function generateGroupCode(): string
    {
        $exists = true;
        
        do {
            // Generate 8-character alphanumeric code (uppercase letters and numbers)
            $code = strtoupper(Str::random(8));
            
            // Ensure it contains both letters and numbers for better uniqueness
            if (!preg_match('/[A-Z]/', $code) || !preg_match('/[0-9]/', $code)) {
                continue;
            }
            
            // Check if code already exists
            $exists = Group::where('group_code', $code)->exists();
        } while ($exists);

        return $code;
    }

    /**
     * Indicate that the group is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays($attributes['cycle_days']),
            'current_members' => $attributes['total_members'],
        ]);
    }

    /**
     * Indicate that the group is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'start_date' => now()->subDays($attributes['cycle_days']),
            'end_date' => now(),
            'current_members' => $attributes['total_members'],
        ]);
    }

    /**
     * Indicate that the group is full.
     */
    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_members' => $attributes['total_members'],
        ]);
    }

    /**
     * Set specific contribution amount.
     */
    public function withContributionAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'contribution_amount' => $amount,
        ]);
    }

    /**
     * Set specific member count.
     */
    public function withMembers(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'total_members' => $count,
            'cycle_days' => $count,
        ]);
    }
}
