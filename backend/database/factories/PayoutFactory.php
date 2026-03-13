<?php

namespace Database\Factories;

use App\Models\Payout;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payout>
 */
class PayoutFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Payout::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'payout_day' => fake()->numberBetween(1, 30),
            'payout_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => 'pending',
            'payout_method' => fake()->randomElement(['wallet', 'bank_transfer']),
            'payout_reference' => 'PAYOUT-' . strtoupper(fake()->unique()->bothify('??##??##??##')),
            'failure_reason' => null,
            'processed_at' => null,
        ];
    }

    /**
     * Indicate that the payout is successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'successful',
            'processed_at' => fake()->dateTimeBetween($attributes['payout_date'], 'now'),
        ]);
    }

    /**
     * Indicate that the payout is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_at' => null,
        ]);
    }

    /**
     * Indicate that the payout is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => fake()->sentence(),
            'processed_at' => fake()->dateTimeBetween($attributes['payout_date'], 'now'),
        ]);
    }

    /**
     * Indicate that the payout is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed_at' => null,
        ]);
    }

    /**
     * Set the payout for a specific group.
     */
    public function forGroup(Group $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group->id,
            'amount' => $group->contribution_amount * $group->total_members,
        ]);
    }

    /**
     * Set the payout for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set the payout date.
     */
    public function onDate(string|\DateTime $date): static
    {
        return $this->state(fn (array $attributes) => [
            'payout_date' => $date,
        ]);
    }

    /**
     * Set the payout method to wallet.
     */
    public function viaWallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'payout_method' => 'wallet',
        ]);
    }

    /**
     * Set the payout method to bank transfer.
     */
    public function viaBankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payout_method' => 'bank_transfer',
        ]);
    }
}
