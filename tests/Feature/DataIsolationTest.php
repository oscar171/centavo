<?php

use App\Models\Account;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;

it('scopes model queries to the authenticated user automatically', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = Account::factory()->for($user)->create();
    $theirs = Account::factory()->for($other)->count(3)->create();

    $statement = Statement::factory()->for($mine)->create();
    Statement::factory()->for($theirs->first())->create();
    Transaction::factory()->for($statement)->for($mine)->count(4)->create();
    Transaction::factory()->for(Statement::factory()->for($theirs->first())->create())->for($theirs->first())->count(5)->create();

    $this->actingAs($user);

    // Even a completely unscoped query only ever returns the user's own rows.
    expect(Account::count())->toBe(1)
        ->and(Account::pluck('id')->all())->toBe([$mine->id])
        ->and(Statement::count())->toBe(1)
        ->and(Transaction::count())->toBe(4);
});

it('does not scope model queries without an authenticated user', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->count(2)->create();
    Account::factory()->for(User::factory())->count(3)->create();

    // A queue job, seeder or console command has no authenticated user, so it
    // sees every row (scoping is an authenticated-request safeguard only).
    expect(Account::count())->toBe(5);
});
