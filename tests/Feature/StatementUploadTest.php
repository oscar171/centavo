<?php

use App\Enums\StatementStatus;
use App\Jobs\ProcessStatement;
use App\Models\Account;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('stores an uploaded pdf and dispatches the processing job', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

    $response = $this->actingAs($user)
        ->post(route('statements.store', $account), ['file' => $file]);

    $statement = Statement::first();

    expect($statement)->not->toBeNull()
        ->and($statement->account_id)->toBe($account->id)
        ->and($statement->original_filename)->toBe('statement.pdf')
        ->and($statement->status)->toBe(StatementStatus::Pending)
        ->and($statement->file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($statement->file_path);
    Queue::assertPushed(ProcessStatement::class, fn ($job) => $job->statement->is($statement));

    $response->assertRedirect(route('statements.show', $statement));
});

it('rejects non-pdf uploads', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $file = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

    $this->actingAs($user)
        ->post(route('statements.store', $account), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(Statement::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('requires a file when uploading a statement', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('statements.store', $account), [])
        ->assertSessionHasErrors('file');
});

it('prevents uploading to another users account', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($other)->create();

    $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

    $this->actingAs($user)
        ->post(route('statements.store', $account), ['file' => $file])
        ->assertNotFound();

    expect(Statement::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('lets a user view their own statement', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    $this->actingAs($user)
        ->get(route('statements.show', $statement))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('statements/show')
            ->where('statement.uuid', $statement->uuid)
        );
});

it('routes statements by uuid instead of the numeric id', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    $this->actingAs($user);

    $this->get('/statements/'.$statement->uuid)->assertOk();
    $this->get('/statements/'.$statement->id)->assertNotFound();
});

it('forbids viewing another users statement', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($other)->create();
    $statement = Statement::factory()->for($account)->create();

    $this->actingAs($user)
        ->get(route('statements.show', $statement))
        ->assertNotFound();
});

it('reprocesses a statement with a new pdf', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->needsReview()->create([
        'file_path' => 'statements/old.pdf',
        'failure_reason' => null,
    ]);

    $file = UploadedFile::fake()->create('nuevo.pdf', 100, 'application/pdf');

    $this->actingAs($user)
        ->post(route('statements.reprocess', $statement), ['file' => $file])
        ->assertRedirect(route('statements.show', $statement));

    $statement->refresh();

    expect($statement->status)->toBe(StatementStatus::Pending)
        ->and($statement->original_filename)->toBe('nuevo.pdf')
        ->and($statement->is_reconciled)->toBeFalse();

    Storage::disk('local')->assertExists($statement->file_path);
    Queue::assertPushed(ProcessStatement::class, fn ($job) => $job->statement->is($statement));
});

it('rejects a non-pdf when reprocessing', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->failed()->create();

    $file = UploadedFile::fake()->create('nope.exe', 100, 'application/octet-stream');

    $this->actingAs($user)
        ->post(route('statements.reprocess', $statement), ['file' => $file])
        ->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});

it('prevents reprocessing another users statement', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($other)->create();
    $statement = Statement::factory()->for($account)->create();

    $file = UploadedFile::fake()->create('x.pdf', 100, 'application/pdf');

    $this->actingAs($user)
        ->post(route('statements.reprocess', $statement), ['file' => $file])
        ->assertNotFound();

    Queue::assertNothingPushed();
});

it('shows the standalone upload page with the user accounts', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->count(2)->create();
    Account::factory()->create(); // another user's account

    $this->actingAs($user)
        ->get(route('statements.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('statements/create')
            ->has('accounts', 2)
        );
});
