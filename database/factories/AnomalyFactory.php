<?php

namespace Database\Factories;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\Account;
use App\Models\Anomaly;
use App\Models\Statement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Anomaly>
 */
class AnomalyFactory extends Factory
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
            'account_id' => Account::factory(),
            'statement_id' => Statement::factory(),
            'type' => fake()->randomElement(AnomalyType::cases()),
            'severity' => fake()->randomElement(AnomalySeverity::cases()),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'transaction_ids' => [],
            'metadata' => [],
            'status' => 'open',
        ];
    }
}
