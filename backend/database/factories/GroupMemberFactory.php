<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupMember>
 */
class GroupMemberFactory extends Factory
{
    protected $model = GroupMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $positionCounter = [];
        
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'position_number' => function (array $attributes) use (&$positionCounter) {
                $groupId = $attributes['group_id'];
                if (!isset($positionCounter[$groupId])) {
                    $positionCounter[$groupId] = 0;
                }
                return $positionCounter[$groupId]++;
            },
            'payout_day' => 0, // Will be calculated when group starts
            'has_received_payout' => false,
            'payout_received_at' => null,
            'joined_at' => now(),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the member has received their payout.
     */
    public function receivedPayout(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_received_payout' => true,
            'payout_received_at' => now(),
        ]);
    }

    /**
     * Indicate that the member has been removed from the group.
     */
    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'removed',
        ]);
    }

    /**
     * Indicate that the member has left the group.
     */
    public function left(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'left',
        ]);
    }

    /**
     * Set a specific position number for the member.
     */
    public function withPosition(int $position, int $payoutDay): static
    {
        return $this->state(fn (array $attributes) => [
            'position_number' => $position,
            'payout_day' => $payoutDay,
        ]);
    }
}
