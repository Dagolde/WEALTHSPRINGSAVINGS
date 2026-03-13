<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BankAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_name' => fake()->name(),
            'account_number' => fake()->numerify('##########'), // 10 digit account number
            'bank_name' => fake()->randomElement([
                'First Bank',
                'Access Bank',
                'GTBank',
                'Zenith Bank',
                'UBA',
                'Fidelity Bank',
                'Union Bank',
                'Stanbic IBTC',
                'Sterling Bank',
                'Wema Bank',
            ]),
            'bank_code' => fake()->randomElement([
                '011', // First Bank
                '044', // Access Bank
                '058', // GTBank
                '057', // Zenith Bank
                '033', // UBA
                '070', // Fidelity Bank
                '032', // Union Bank
                '221', // Stanbic IBTC
                '232', // Sterling Bank
                '035', // Wema Bank
            ]),
            'is_verified' => false,
            'is_primary' => false,
        ];
    }

    /**
     * Indicate that the bank account is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }

    /**
     * Indicate that the bank account is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
