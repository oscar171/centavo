<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\StatementStatus;
use App\Enums\TransactionCategory;
use App\Models\Account;
use App\Models\User;
use App\Services\AnomalyDetector;
use App\Services\StatementReconciler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed a demo user with realistic accounts, statements, transactions and
     * anomalies so the whole app can be explored with data.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Oscar',
            'email' => 'demo@centavo.test',
            'password' => Hash::make('password'),
        ]);

        // --- Cuenta principal: Wells Fargo, el caso real (ráfaga de FanDuel) ---
        $wellsFargo = $user->accounts()->create([
            'name' => 'Cuenta principal',
            'bank' => 'Wells Fargo',
            'account_type' => AccountType::Checking,
            'last_four' => '4821',
            'currency' => 'USD',
        ]);

        $march = require base_path('tests/Fixtures/wells_fargo_march.php');
        $this->seedStatement($wellsFargo, $march, 'wells-fargo-marzo-2025.pdf');

        // --- Segunda cuenta: Chase, un statement limpio y otro en revisión ---
        $chase = $user->accounts()->create([
            'name' => 'Tarjeta Chase',
            'bank' => 'Chase',
            'account_type' => AccountType::Credit,
            'last_four' => '3390',
            'currency' => 'USD',
        ]);

        $this->seedStatement($chase, $this->cleanFebruary(), 'chase-febrero-2025.pdf');
        $this->seedStatement($chase, $this->mismatchedApril(), 'chase-abril-2025.pdf');
    }

    /**
     * Persist an extractor-shaped statement, then reconcile and detect
     * anomalies exactly like the real processing pipeline (no AI call).
     *
     * @param  array<string, mixed>  $data
     */
    private function seedStatement(Account $account, array $data, string $filename): void
    {
        $statement = $account->statements()->create([
            'original_filename' => $filename,
            'file_path' => null,
            'status' => StatementStatus::Pending,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'beginning_balance' => $data['beginning_balance'],
            'ending_balance' => $data['ending_balance'],
            'total_deposits' => $data['total_deposits'],
            'total_withdrawals' => $data['total_withdrawals'],
        ]);

        foreach ($data['transactions'] as $transaction) {
            $statement->transactions()->create([
                'account_id' => $account->id,
                'date' => $transaction['date'],
                'description' => $transaction['description'],
                'amount' => abs((float) $transaction['amount']),
                'direction' => $transaction['direction'],
                'running_balance' => $transaction['running_balance'] ?? null,
                'reference' => $transaction['reference'] ?? null,
                'merchant' => $transaction['merchant'] ?? null,
                'category' => $this->categoryFor($transaction),
            ]);
        }

        app(StatementReconciler::class)->reconcile($statement);
        app(AnomalyDetector::class)->detect($statement);
    }

    /**
     * Best-effort category for a demo transaction: explicit if provided, income
     * for credits, otherwise inferred from the merchant.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function categoryFor(array $transaction): string
    {
        if (! empty($transaction['category'])) {
            return $transaction['category'];
        }

        if (($transaction['direction'] ?? null) === 'credit') {
            return TransactionCategory::Income->value;
        }

        return match ($transaction['merchant'] ?? null) {
            'Landlord' => TransactionCategory::Housing->value,
            'Whole Foods' => TransactionCategory::Food->value,
            'Amazon', 'Mercari', 'Walmart' => TransactionCategory::Shopping->value,
            'FanDuel' => TransactionCategory::Entertainment->value,
            'Netflix', 'OpenAI' => TransactionCategory::Subscriptions->value,
            'Uber' => TransactionCategory::Transport->value,
            'Geico' => TransactionCategory::Health->value,
            default => TransactionCategory::Other->value,
        };
    }

    /**
     * A tidy, fully reconciled statement with no anomalies.
     *
     * @return array<string, mixed>
     */
    private function cleanFebruary(): array
    {
        return [
            'period_start' => '2025-02-01',
            'period_end' => '2025-02-28',
            'beginning_balance' => 1200.00,
            'ending_balance' => 1700.00,
            'total_deposits' => 3000.00,
            'total_withdrawals' => 2500.00,
            'transactions' => [
                ['date' => '2025-02-01', 'description' => 'DIRECT DEPOSIT PAYROLL', 'amount' => 3000.00, 'direction' => 'credit', 'merchant' => 'Employer'],
                ['date' => '2025-02-02', 'description' => 'ONLINE TRANSFER TO LANDLORD RENT', 'amount' => 1500.00, 'direction' => 'debit', 'merchant' => 'Landlord'],
                ['date' => '2025-02-05', 'description' => 'PURCHASE AUTHORIZED WHOLE FOODS MARKET', 'amount' => 400.00, 'direction' => 'debit', 'merchant' => 'Whole Foods'],
                ['date' => '2025-02-12', 'description' => 'PURCHASE AUTHORIZED AMAZON.COM', 'amount' => 600.00, 'direction' => 'debit', 'merchant' => 'Amazon'],
            ],
        ];
    }

    /**
     * A statement whose balances do not add up, so it lands in needs_review.
     *
     * @return array<string, mixed>
     */
    private function mismatchedApril(): array
    {
        // Expected end = 1700 + 3000 - 1500 = 3200, but the statement claims 3500.
        return [
            'period_start' => '2025-04-01',
            'period_end' => '2025-04-30',
            'beginning_balance' => 1700.00,
            'ending_balance' => 3500.00,
            'total_deposits' => 3000.00,
            'total_withdrawals' => 1500.00,
            'transactions' => [
                ['date' => '2025-04-01', 'description' => 'DIRECT DEPOSIT PAYROLL', 'amount' => 3000.00, 'direction' => 'credit', 'merchant' => 'Employer'],
                ['date' => '2025-04-03', 'description' => 'ONLINE TRANSFER TO LANDLORD RENT', 'amount' => 1500.00, 'direction' => 'debit', 'merchant' => 'Landlord'],
            ],
        ];
    }
}
