<?php

use App\Ai\Agents\StatementExtractor;
use App\Enums\StatementStatus;
use App\Enums\TransactionDirection;
use App\Jobs\ProcessStatement;
use App\Models\Account;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Create a pending statement with a placeholder PDF on the private disk.
 */
function pendingStatement(): Statement
{
    $account = Account::factory()->for(User::factory())->create(['currency' => 'USD']);
    $path = "statements/{$account->id}/march.pdf";

    Storage::disk('local')->put($path, '%PDF-1.4 test');

    return Statement::factory()->for($account)->create([
        'file_path' => $path,
        'status' => StatementStatus::Pending,
    ]);
}

/**
 * @return array<string, mixed>
 */
function wellsFargoFixture(): array
{
    return require base_path('tests/Fixtures/wells_fargo_march.php');
}

it('extracts a statement and persists its header and transactions', function () {
    Storage::fake('local');
    StatementExtractor::fake([wellsFargoFixture()]);

    $statement = pendingStatement();

    ProcessStatement::dispatchSync($statement);

    $statement->refresh();

    expect($statement->transactions()->count())->toBe(57)
        ->and($statement->status)->toBe(StatementStatus::Processed)
        ->and($statement->beginning_balance)->toBe('9166.13')
        ->and($statement->ending_balance)->toBe('9666.41')
        ->and($statement->total_deposits)->toBe('16652.72')
        ->and($statement->total_withdrawals)->toBe('16152.44')
        ->and($statement->period_start->toDateString())->toBe('2025-03-01');

    StatementExtractor::assertPrompted(fn ($prompt) => $prompt->contains('movimientos'));
});

it('stores every amount as a positive value with a normalized direction', function () {
    Storage::fake('local');
    StatementExtractor::fake([wellsFargoFixture()]);

    $statement = pendingStatement();

    ProcessStatement::dispatchSync($statement);

    expect($statement->transactions()->where('amount', '<=', 0)->count())->toBe(0)
        ->and($statement->transactions()->where('direction', TransactionDirection::Credit)->count())->toBe(6)
        ->and($statement->transactions()->where('direction', TransactionDirection::Debit)->count())->toBe(51);
});

it('is idempotent when the job is retried', function () {
    Storage::fake('local');
    StatementExtractor::fake([wellsFargoFixture(), wellsFargoFixture()]);

    $statement = pendingStatement();

    ProcessStatement::dispatchSync($statement);
    ProcessStatement::dispatchSync($statement);

    expect($statement->transactions()->count())->toBe(57);
});

it('tells the extractor about the users custom categories', function () {
    $extractor = new StatementExtractor(['Mascotas', 'Donaciones']);

    expect((string) $extractor->instructions())
        ->toContain('personalizadas')
        ->toContain('Mascotas')
        ->toContain('Donaciones');
});

it('omits the custom category section when the user has none', function () {
    expect((string) (new StatementExtractor)->instructions())
        ->not->toContain('personalizadas');
});

it('marks the statement as failed when extraction throws', function () {
    Storage::fake('local');
    StatementExtractor::fake(function () {
        throw new RuntimeException('extraction failed');
    });

    $statement = pendingStatement();

    expect(fn () => ProcessStatement::dispatchSync($statement))
        ->toThrow(RuntimeException::class);

    expect($statement->fresh()->status)->toBe(StatementStatus::Failed)
        ->and($statement->fresh()->failure_reason)->toBe('extraction failed');
});
