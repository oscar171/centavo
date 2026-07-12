<?php

use App\Ai\Agents\StatementExtractor;
use App\Jobs\ProcessStatement;
use App\Models\Account;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, mixed>
 */
function retentionFixture(): array
{
    return require base_path('tests/Fixtures/wells_fargo_march.php');
}

function statementWithPdf(): Statement
{
    $account = Account::factory()->for(User::factory())->create();
    $path = "statements/{$account->id}/march.pdf";
    Storage::disk('local')->put($path, '%PDF-1.4 test');

    return Statement::factory()->for($account)->create(['file_path' => $path]);
}

it('deletes the pdf after processing when retention is disabled', function () {
    config(['centavo.delete_pdf_after_processing' => true]);
    Storage::fake('local');
    StatementExtractor::fake([retentionFixture()]);

    $statement = statementWithPdf();
    $path = $statement->file_path;

    ProcessStatement::dispatchSync($statement);

    Storage::disk('local')->assertMissing($path);
    expect($statement->fresh()->file_path)->toBeNull();
});

it('keeps the pdf when retention is enabled', function () {
    config(['centavo.delete_pdf_after_processing' => false]);
    Storage::fake('local');
    StatementExtractor::fake([retentionFixture()]);

    $statement = statementWithPdf();
    $path = $statement->file_path;

    ProcessStatement::dispatchSync($statement);

    Storage::disk('local')->assertExists($path);
    expect($statement->fresh()->file_path)->toBe($path);
});

it('stores uploaded pdfs on the private disk only', function () {
    expect(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
        ->and(config('filesystems.disks.local'))->not->toHaveKey('url');
});

it('rate limits statement uploads', function () {
    $route = Route::getRoutes()->getByName('statements.store');

    expect($route->middleware())->toContain('throttle:20,1');
});

it('guards every account and statement route behind auth', function () {
    $account = Account::factory()->create();
    $statement = Statement::factory()->for($account)->create();

    $this->get(route('accounts.index'))->assertRedirect(route('login'));
    $this->get(route('accounts.show', $account))->assertRedirect(route('login'));
    $this->get(route('statements.show', $statement))->assertRedirect(route('login'));
    $this->post(route('statements.store', $account))->assertRedirect(route('login'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
