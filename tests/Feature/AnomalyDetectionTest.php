<?php

use App\Ai\Agents\StatementExtractor;
use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Jobs\ProcessStatement;
use App\Models\Account;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AnomalyDetector;
use Illuminate\Support\Facades\Storage;

it('detects a charge burst', function () {
    $statement = Statement::factory()->create();

    Transaction::factory()->for($statement)->debit()->count(44)->create([
        'merchant' => 'FanDuel',
        'amount' => 100.00,
        'date' => '2025-03-06',
    ]);

    (new AnomalyDetector)->detect($statement);

    $burst = $statement->anomalies()->where('type', AnomalyType::ChargeBurst)->get();

    expect($burst)->toHaveCount(1)
        ->and($burst->first()->severity)->toBe(AnomalySeverity::High)
        ->and($burst->first()->amount)->toBe('4400.00')
        ->and($burst->first()->transaction_ids)->toHaveCount(44);
});

it('detects duplicate charges', function () {
    $statement = Statement::factory()->create();

    // Two identical Mercari charges the same day (not enough for a burst).
    Transaction::factory()->for($statement)->debit()->count(2)->create([
        'merchant' => 'Mercari',
        'amount' => 2769.97,
        'date' => '2025-03-10',
    ]);

    (new AnomalyDetector)->detect($statement);

    $duplicates = $statement->anomalies()->where('type', AnomalyType::DuplicateCharge)->get();

    expect($duplicates)->toHaveCount(1)
        ->and($duplicates->first()->severity)->toBe(AnomalySeverity::Medium)
        ->and($duplicates->first()->transaction_ids)->toHaveCount(2);
});

it('does not flag a burst as a duplicate charge', function () {
    $statement = Statement::factory()->create();

    Transaction::factory()->for($statement)->debit()->count(6)->create([
        'merchant' => 'FanDuel',
        'amount' => 100.00,
        'date' => '2025-03-06',
    ]);

    (new AnomalyDetector)->detect($statement);

    expect($statement->anomalies()->where('type', AnomalyType::ChargeBurst)->count())->toBe(1)
        ->and($statement->anomalies()->where('type', AnomalyType::DuplicateCharge)->count())->toBe(0);
});

it('links reversals to original charges', function () {
    $statement = Statement::factory()->create();

    $charge = Transaction::factory()->for($statement)->debit()->create([
        'merchant' => 'Mercari',
        'amount' => 2769.97,
        'date' => '2025-03-10',
        'reference' => 'MK000002',
        'description' => 'PURCHASE AUTHORIZED MERCARI',
    ]);

    $refund = Transaction::factory()->for($statement)->credit()->create([
        'merchant' => 'Mercari',
        'amount' => 2769.97,
        'date' => '2025-03-28',
        'reference' => 'MK000002',
        'description' => 'PURCHASE RETURN MERCARI',
    ]);

    (new AnomalyDetector)->detect($statement);

    $reversal = $statement->anomalies()->where('type', AnomalyType::Reversal)->first();

    expect($reversal)->not->toBeNull()
        ->and($reversal->severity)->toBe(AnomalySeverity::Low)
        ->and($reversal->transaction_ids)->toContain($charge->id)
        ->and($reversal->transaction_ids)->toContain($refund->id);
});

it('detects a reversal by description even without a matching charge', function () {
    $statement = Statement::factory()->create();

    Transaction::factory()->for($statement)->credit()->create([
        'merchant' => 'FanDuel',
        'amount' => 4400.00,
        'description' => 'PROVISIONAL CREDIT FANDUEL DISPUTE',
        'reference' => null,
    ]);

    (new AnomalyDetector)->detect($statement);

    expect($statement->anomalies()->where('type', AnomalyType::Reversal)->count())->toBe(1);
});

it('clears previous anomalies on re-detection', function () {
    $statement = Statement::factory()->create();

    Transaction::factory()->for($statement)->debit()->count(44)->create([
        'merchant' => 'FanDuel',
        'amount' => 100.00,
        'date' => '2025-03-06',
    ]);

    (new AnomalyDetector)->detect($statement);
    (new AnomalyDetector)->detect($statement);

    expect($statement->anomalies()->where('type', AnomalyType::ChargeBurst)->count())->toBe(1);
});

it('detects the real fanduel burst and mercari reversals end to end', function () {
    Storage::fake('local');
    StatementExtractor::fake([require base_path('tests/Fixtures/wells_fargo_march.php')]);

    $account = Account::factory()->for(User::factory())->create();
    $path = "statements/{$account->id}/march.pdf";
    Storage::disk('local')->put($path, '%PDF-1.4 test');

    $statement = Statement::factory()->for($account)->create([
        'file_path' => $path,
    ]);

    ProcessStatement::dispatchSync($statement);

    expect($statement->anomalies()->where('type', AnomalyType::ChargeBurst)->count())->toBe(1)
        ->and($statement->anomalies()->where('type', AnomalyType::DuplicateCharge)->count())->toBe(1)
        ->and($statement->anomalies()->where('type', AnomalyType::Reversal)->count())->toBe(2);

    $burst = $statement->anomalies()->where('type', AnomalyType::ChargeBurst)->first();

    expect($burst->transaction_ids)->toHaveCount(44)
        ->and($burst->amount)->toBe('4400.00');
});
