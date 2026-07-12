<?php

use App\Ai\Agents\StatementExtractor;
use App\Enums\StatementStatus;
use App\Jobs\ProcessStatement;
use App\Models\Account;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StatementReconciler;
use Illuminate\Support\Facades\Storage;

it('marks a statement as reconciled when balances add up', function () {
    $statement = Statement::factory()->create([
        'beginning_balance' => 9166.13,
        'ending_balance' => 9666.41,
        'total_deposits' => 16652.72,
        'total_withdrawals' => 16152.44,
        'status' => StatementStatus::Processing,
        'is_reconciled' => false,
    ]);

    Transaction::factory()->for($statement)->credit()->create(['amount' => 16652.72]);
    Transaction::factory()->for($statement)->debit()->create(['amount' => 16152.44]);

    (new StatementReconciler)->reconcile($statement);

    $statement->refresh();

    expect($statement->is_reconciled)->toBeTrue()
        ->and($statement->status)->toBe(StatementStatus::Processed)
        ->and($statement->reconciliation_diff)->toBe('0.00')
        ->and($statement->processed_at)->not->toBeNull();
});

it('flags needs_review when balances do not add up', function () {
    $statement = Statement::factory()->create([
        'beginning_balance' => 1000.00,
        'ending_balance' => 2000.00,
        'total_deposits' => 500.00,
        'total_withdrawals' => 0.00,
        'status' => StatementStatus::Processing,
        'is_reconciled' => false,
    ]);

    // Expected end = 1000 + 500 - 0 = 1500, but the statement claims 2000.
    Transaction::factory()->for($statement)->credit()->create(['amount' => 500.00]);

    (new StatementReconciler)->reconcile($statement);

    $statement->refresh();

    expect($statement->is_reconciled)->toBeFalse()
        ->and($statement->status)->toBe(StatementStatus::NeedsReview)
        ->and($statement->reconciliation_diff)->toBe('-500.00');
});

it('flags needs_review when the deposit total does not match the credits', function () {
    $statement = Statement::factory()->create([
        'beginning_balance' => 100.00,
        'ending_balance' => 200.00,
        'total_deposits' => 999.00, // wrong: credits actually sum to 100
        'total_withdrawals' => 0.00,
        'status' => StatementStatus::Processing,
        'is_reconciled' => false,
    ]);

    Transaction::factory()->for($statement)->credit()->create(['amount' => 100.00]);

    (new StatementReconciler)->reconcile($statement);

    expect($statement->fresh()->is_reconciled)->toBeFalse()
        ->and($statement->fresh()->status)->toBe(StatementStatus::NeedsReview);
});

it('reconciles the real wells fargo march statement end to end', function () {
    Storage::fake('local');
    StatementExtractor::fake([require base_path('tests/Fixtures/wells_fargo_march.php')]);

    $account = Account::factory()->for(User::factory())->create();
    $path = "statements/{$account->id}/march.pdf";
    Storage::disk('local')->put($path, '%PDF-1.4 test');

    $statement = Statement::factory()->for($account)->create([
        'file_path' => $path,
        'status' => StatementStatus::Pending,
    ]);

    ProcessStatement::dispatchSync($statement);

    $statement->refresh();

    expect($statement->status)->toBe(StatementStatus::Processed)
        ->and($statement->is_reconciled)->toBeTrue()
        ->and($statement->reconciliation_diff)->toBe('0.00');
});
