<?php

namespace Database\Factories;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contribution>
 */
class ContributionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Contribution::class;

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
            'amount' => fake()->randomFloat(2, 100, 10000),
            'payment_method' => fake()->randomElement(['wallet', 'card', 'bank_transfer']),
            'payment_reference' => 'PAY-' . strtoupper(fake()->unique()->bothify('??##??##??##')),
            'payment_status' => 'pending',
            'contribution_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'paid_at' => null,
        ];
    }

    /**
     * Indicate that the contribution is successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'successful',
            'paid_at' => fake()->dateTimeBetween($attributes['contribution_date'], 'now'),
        ]);
    }

    /**
     * Indicate that the contribution is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'pending',
            'paid_at' => null,
        ]);
    }

    /**
     * Indicate that the contribution is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'failed',
            'paid_at' => null,
        ]);
    }

    /**
     * Set the contribution for a specific group.
     */
    public function forGroup(Group $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group->id,
            'amount' => $group->contribution_amount,
        ]);
    }

    /**
     * Set the contribution for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set the contribution date.
     */
    public function onDate(string|\DateTime $date): static
    {
        return $this->state(fn (array $attributes) => [
            'contribution_date' => $date,
        ]);
    }

    /**
     * Set the payment method to wallet.
     */
    public function viaWallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'wallet',
        ]);
    }

    /**
     * Set the payment method to card.
     */
    public function viaCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'card',
        ]);
    }

    /**
     * Set the payment method to bank transfer.
     */
    public function viaBankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'bank_transfer',
        ]);
    }
}

