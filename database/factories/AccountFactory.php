<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Cuenta principal', 'Cuenta de ahorros', 'Tarjeta de crédito']),
            'bank' => fake()->randomElement(config('banks')),
            'account_type' => fake()->randomElement(AccountType::cases()),
            'last_four' => (string) fake()->numberBetween(1000, 9999),
            'currency' => 'USD',
        ];
    }
}
