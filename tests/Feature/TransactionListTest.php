<?php

use App\Models\Account;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;

it('lists the current user transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->count(3)->create();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transactions/index')
            ->has('transactions.data', 3)
            ->where('transactions.total', 3)
        );
});

it('excludes other users transactions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();
    Transaction::factory()->for($statement)->for($account)->create();

    $otherAccount = Account::factory()->for($other)->create();
    $otherStatement = Statement::factory()->for($otherAccount)->create();
    Transaction::factory()->for($otherStatement)->for($otherAccount)->count(5)->create();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page->where('transactions.total', 1));
});

it('scopes transactions to the selected account', function () {
    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();

    $statementA = Statement::factory()->for($accountA)->create();
    $statementB = Statement::factory()->for($accountB)->create();

    Transaction::factory()->for($statementA)->for($accountA)->count(2)->create();
    Transaction::factory()->for($statementB)->for($accountB)->count(4)->create();

    $this->actingAs($user)
        ->get(route('transactions.index', ['account' => $accountB->uuid]))
        ->assertInertia(fn ($page) => $page
            ->where('selectedAccount', $accountB->uuid)
            ->where('transactions.total', 4)
        );
});

it('filters transactions by direction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->credit()->count(2)->create();
    Transaction::factory()->for($statement)->for($account)->debit()->count(3)->create();

    $this->actingAs($user)
        ->get(route('transactions.index', ['direction' => 'debit']))
        ->assertInertia(fn ($page) => $page->where('transactions.total', 3));
});

it('filters transactions by category and amount range', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 50.00, 'category' => 'food']);
    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 500.00, 'category' => 'food']);
    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 500.00, 'category' => 'Mascotas']);

    $this->actingAs($user)
        ->get(route('transactions.index', ['category' => 'food', 'min' => 100]))
        ->assertInertia(fn ($page) => $page->where('transactions.total', 1));
});

it('searches transactions by description, merchant or category', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->create(['description' => 'AMAZON WEB SERVICES', 'merchant' => 'AWS']);
    Transaction::factory()->for($statement)->for($account)->create(['description' => 'STARBUCKS STORE', 'merchant' => 'Starbucks']);

    $this->actingAs($user)
        ->get(route('transactions.index', ['q' => 'amazon']))
        ->assertInertia(fn ($page) => $page->where('transactions.total', 1));
});

it('summarizes income, expenses and net for the filtered set', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->credit()->create(['amount' => 1000.00]);
    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 400.00]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.income', 1000)
            ->where('summary.expense', 400)
            ->where('summary.net', 600)
            ->where('summary.count', 2)
        );
});

it('requires authentication', function () {
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
});
