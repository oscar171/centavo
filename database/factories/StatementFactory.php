<?php

namespace Database\Factories;

use App\Enums\StatementStatus;
use App\Models\Account;
use App\Models\Statement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Statement>
 */
class StatementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', '-1 month');
        $end = (clone $start)->modify('+1 month');
        $beginning = fake()->randomFloat(2, 500, 10000);
        $deposits = fake()->randomFloat(2, 0, 15000);
        $withdrawals = fake()->randomFloat(2, 0, 15000);

        return [
            'uuid' => fake()->uuid(),
            'account_id' => Account::factory(),
            'period_start' => $start,
            'period_end' => $end,
            'beginning_balance' => $beginning,
            'ending_balance' => round($beginning + $deposits - $withdrawals, 2),
            'total_deposits' => $deposits,
            'total_withdrawals' => $withdrawals,
            'original_filename' => fake()->slug(3).'.pdf',
            'file_path' => 'statements/'.fake()->uuid().'.pdf',
            'status' => StatementStatus::Pending,
            'is_reconciled' => false,
            'reconciliation_diff' => null,
            'failure_reason' => null,
            'processed_at' => null,
        ];
    }

    /**
     * A statement that was processed and whose balances reconciled.
     */
    public function reconciled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatementStatus::Processed,
            'is_reconciled' => true,
            'reconciliation_diff' => 0,
            'processed_at' => now(),
        ]);
    }

    /**
     * A statement whose balances did not add up and needs manual review.
     */
    public function needsReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatementStatus::NeedsReview,
            'is_reconciled' => false,
            'reconciliation_diff' => fake()->randomFloat(2, 1, 500),
            'processed_at' => now(),
        ]);
    }

    /**
     * A statement whose processing failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatementStatus::Failed,
            'failure_reason' => 'No se pudo extraer el contenido del PDF.',
        ]);
    }

    /**
     * A statement still waiting in the queue.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatementStatus::Processing,
        ]);
    }
}
