<?php

use App\Models\Account;
use App\Models\Anomaly;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

it('aggregates income, expenses and net for the current user', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->credit()->create(['amount' => 3000.00]);
    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 1200.00, 'merchant' => 'Amazon']);
    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 800.00, 'merchant' => 'Uber']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('summary.income', 3000)
            ->where('summary.expense', 2000)
            ->where('summary.net', 1000)
        );
});

it('defers the heavy dashboard widgets on the initial load', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('summary')
            ->missing('monthly')
            ->missing('spendingByMerchant')
            ->missing('recentStatements')
        );
});

it('ranks spending by merchant descending (deferred)', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 500.00, 'merchant' => 'Amazon']);
    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 500.00, 'merchant' => 'Amazon']);
    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 300.00, 'merchant' => 'Uber']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->loadDeferredProps('charts', fn ($reload) => $reload
                ->has('spendingByMerchant.merchants', 2)
                ->where('spendingByMerchant.merchants.0.name', 'Amazon')
                ->where('spendingByMerchant.merchants.0.total', 1000)
                ->where('spendingByMerchant.merchants.0.key', 'm0')
                ->where('spendingByMerchant.merchants.1.name', 'Uber')
                ->has('spendingByMerchant.series')
            )
        );
});

it('builds a monthly income and expense series (deferred)', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->credit()->create(['amount' => 200.00, 'date' => '2025-01-10']);
    Transaction::factory()->for($statement)->for($account)->debit()->create(['amount' => 50.00, 'merchant' => 'X', 'date' => '2025-01-20']);
    Transaction::factory()->for($statement)->for($account)->credit()->create(['amount' => 300.00, 'date' => '2025-02-10']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('charts', fn ($reload) => $reload
                ->has('monthly', 2)
                ->where('monthly.0.month', '2025-01')
                ->where('monthly.0.income', 200)
                ->where('monthly.0.expense', 50)
                ->where('monthly.1.month', '2025-02')
                ->where('monthly.1.income', 300)
            )
        );
});

it('includes the most recent statements (deferred)', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    Statement::factory()->for($account)->count(8)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('activity', fn ($reload) => $reload->has('recentStatements', 6))
        );
});

it('does not expose anomalies on the dashboard', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Anomaly::factory()->for($account)->for($statement)->create(['status' => 'open']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('activity', fn ($reload) => $reload
                ->has('recentStatements')
                ->missing('anomalies')
                ->missing('openAnomaliesCount')
            )
        );
});

it('windows the summary by range and computes the period-over-period change', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    Transaction::factory()->for($statement)->for($account)->credit()->create(['amount' => 100.00, 'date' => '2025-05-15']);
    Transaction::factory()->for($statement)->for($account)->credit()->create(['amount' => 200.00, 'date' => '2025-06-15']);

    $this->actingAs($user)
        ->get(route('dashboard', ['range' => '1m']))
        ->assertInertia(fn ($page) => $page
            ->where('range', '1m')
            ->where('summary.income', 200)
            ->where('summaryChange.income', 100)
        );
});

it('only aggregates the current user data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();
    Transaction::factory()->for($statement)->for($account)->credit()->create(['amount' => 100.00]);

    $otherAccount = Account::factory()->for($other)->create();
    $otherStatement = Statement::factory()->for($otherAccount)->create();
    Transaction::factory()->for($otherStatement)->for($otherAccount)->credit()->create(['amount' => 9999.00]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('summary.income', 100));
});

it('scopes aggregates to the selected account', function () {
    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();

    $statementA = Statement::factory()->for($accountA)->create();
    $statementB = Statement::factory()->for($accountB)->create();

    Transaction::factory()->for($statementA)->for($accountA)->debit()->create(['amount' => 100.00, 'merchant' => 'A']);
    Transaction::factory()->for($statementB)->for($accountB)->debit()->create(['amount' => 700.00, 'merchant' => 'B']);

    $this->actingAs($user)
        ->get(route('dashboard', ['account' => $accountB->uuid]))
        ->assertInertia(fn ($page) => $page
            ->where('selectedAccount', $accountB->uuid)
            ->where('summary.expense', 700)
        );
});
