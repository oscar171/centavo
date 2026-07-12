<?php

use App\Enums\TransactionCategory;
use App\Models\Account;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;

it('updates the category of a single transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    $transaction = Transaction::factory()->for($statement)->for($account)->create([
        'merchant' => 'Netflix',
        'category' => null,
    ]);
    $sibling = Transaction::factory()->for($statement)->for($account)->create([
        'merchant' => 'Netflix',
        'category' => null,
    ]);

    $this->actingAs($user)
        ->from(route('statements.show', $statement))
        ->patch(route('transactions.category.update', $transaction), [
            'category' => TransactionCategory::Subscriptions->value,
        ])
        ->assertRedirect(route('statements.show', $statement));

    expect($transaction->fresh()->category)->toBe(TransactionCategory::Subscriptions->value)
        ->and($sibling->fresh()->category)->toBeNull();
});

it('accepts a custom category name typed by the user', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();
    $transaction = Transaction::factory()->for($statement)->for($account)->create(['category' => null]);

    $this->actingAs($user)
        ->patch(route('transactions.category.update', $transaction), [
            'category' => 'Mascotas',
        ]);

    expect($transaction->fresh()->category)->toBe('Mascotas');
});

it('applies the category to every transaction of the same merchant', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();

    $first = Transaction::factory()->for($statement)->for($account)->create(['merchant' => 'Netflix', 'category' => null]);
    $second = Transaction::factory()->for($statement)->for($account)->create(['merchant' => 'Netflix', 'category' => null]);
    $unrelated = Transaction::factory()->for($statement)->for($account)->create(['merchant' => 'Amazon', 'category' => null]);

    $this->actingAs($user)
        ->patch(route('transactions.category.update', $first), [
            'category' => TransactionCategory::Subscriptions->value,
            'apply_to_all' => true,
        ]);

    expect($first->fresh()->category)->toBe(TransactionCategory::Subscriptions->value)
        ->and($second->fresh()->category)->toBe(TransactionCategory::Subscriptions->value)
        ->and($unrelated->fresh()->category)->toBeNull();
});

it('requires a non-empty category', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $statement = Statement::factory()->for($account)->create();
    $transaction = Transaction::factory()->for($statement)->for($account)->create();

    $this->actingAs($user)
        ->patch(route('transactions.category.update', $transaction), ['category' => ''])
        ->assertSessionHasErrors('category');
});

it('prevents categorizing another users transaction', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($other)->create();
    $statement = Statement::factory()->for($account)->create();
    $transaction = Transaction::factory()->for($statement)->for($account)->create();

    $this->actingAs($user)
        ->patch(route('transactions.category.update', $transaction), [
            'category' => TransactionCategory::Food->value,
        ])
        ->assertNotFound();
});
