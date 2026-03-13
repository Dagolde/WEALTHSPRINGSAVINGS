<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WalletTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['credit', 'debit']);
        $amount = fake()->randomFloat(2, 100, 10000);
        $balanceBefore = fake()->randomFloat(2, 0, 50000);
        $balanceAfter = $type === 'credit'
            ? $balanceBefore + $amount
            : max(0, $balanceBefore - $amount);

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'purpose' => fake()->sentence(),
            'reference' => 'TXN-' . now()->format('Ymd') . '-' . strtoupper(fake()->bothify('????????')),
            'metadata' => json_encode([
                'source' => fake()->randomElement(['contribution', 'payout', 'withdrawal', 'funding']),
            ]),
            'status' => fake()->randomElement(['pending', 'successful', 'failed']),
        ];
    }

    /**
     * Indicate that the transaction is a credit.
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
        ]);
    }

    /**
     * Indicate that the transaction is a debit.
     */
    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
        ]);
    }

    /**
     * Indicate that the transaction is successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'successful',
        ]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the transaction is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
