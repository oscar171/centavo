<?php

use App\Models\Account;
use App\Models\User;

it('creates an account for the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('accounts.index'))
        ->post(route('accounts.store'), [
            'name' => 'Cuenta principal',
            'bank' => 'Wells Fargo',
            'account_type' => 'checking',
            'last_four' => '1234',
            'currency' => 'USD',
        ]);

    $account = Account::first();

    expect($account)->not->toBeNull()
        ->and($account->user_id)->toBe($user->id)
        ->and($account->name)->toBe('Cuenta principal')
        ->and($account->bank)->toBe('Wells Fargo')
        ->and($account->last_four)->toBe('1234');

    $response->assertRedirect(route('accounts.index'));
});

it('lists only the current user accounts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Account::factory()->for($user)->count(2)->create();
    Account::factory()->for($other)->count(3)->create();

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounts/index')
            ->has('accounts', 2)
        );
});

it('forbids viewing another users account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($other)->create();

    $this->actingAs($user)
        ->get(route('accounts.show', $account))
        ->assertNotFound();
});

it('lets a user view their own account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('accounts.show', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounts/show')
            ->where('account.uuid', $account->uuid)
        );
});

it('validates required fields when creating an account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => '',
            'bank' => '',
        ])
        ->assertSessionHasErrors(['name', 'bank']);
});

it('rejects an invalid last_four', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'Cuenta',
            'bank' => 'Chase',
            'last_four' => '12',
            'currency' => 'USD',
        ])
        ->assertSessionHasErrors('last_four');
});

it('lets a user delete their own account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    expect(Account::find($account->id))->toBeNull();
});

it('forbids deleting another users account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($other)->create();

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $account))
        ->assertNotFound();

    $this->assertDatabaseHas('accounts', ['id' => $account->id]);
});

it('requires authentication to list accounts', function () {
    $this->get(route('accounts.index'))->assertRedirect(route('login'));
});
