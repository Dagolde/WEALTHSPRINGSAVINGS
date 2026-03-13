<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Withdrawal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_account_id' => BankAccount::factory(),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'status' => 'pending',
            'admin_approval_status' => 'pending',
            'payment_reference' => 'WD-' . fake()->unique()->numerify('##########'),
        ];
    }

    /**
     * Indicate that the withdrawal is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'admin_approval_status' => 'approved',
            'status' => 'approved',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the withdrawal is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'admin_approval_status' => 'rejected',
            'status' => 'rejected',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the withdrawal is successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'admin_approval_status' => 'approved',
            'status' => 'successful',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
            'processed_at' => now(),
        ]);
    }
}
