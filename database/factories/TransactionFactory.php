<?php

namespace Database\Factories;

use App\Enums\TransactionDirection;
use App\Models\Account;
use App\Models\Statement;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $merchant = fake()->randomElement(['FanDuel', 'Mercari', 'Amazon', 'Uber', 'Netflix', 'OpenAI', 'Walmart']);

        return [
            'uuid' => fake()->uuid(),
            'statement_id' => Statement::factory(),
            'account_id' => Account::factory(),
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'description' => strtoupper($merchant).' PURCHASE',
            'amount' => fake()->randomFloat(2, 5, 500),
            'direction' => fake()->randomElement(TransactionDirection::cases()),
            'running_balance' => fake()->randomFloat(2, 100, 10000),
            'reference' => (string) fake()->numberBetween(100000000, 999999999),
            'merchant' => $merchant,
            'category' => null,
        ];
    }

    /**
     * A credit (money in) transaction.
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => TransactionDirection::Credit,
        ]);
    }

    /**
     * A debit (money out) transaction.
     */
    public function debit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => TransactionDirection::Debit,
        ]);
    }
}
