<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

function makeAccount(User $user, array $attributes = []): Account
{
    return $user->accounts()->create($attributes + [
        'name' => 'Cash wallet',
        'type' => 'cash',
        'opening_balance' => 0,
    ]);
}

it('redirects guests away from accounts', function () {
    $this->get('/accounts')->assertRedirect('/login');
});

it('lists only the signed-in user accounts', function () {
    $user = User::factory()->create();
    makeAccount($user, ['name' => 'My Wallet']);
    makeAccount(User::factory()->create(), ['name' => 'Someone Elses']);

    $this->actingAs($user)->get('/accounts')
        ->assertOk()
        ->assertSee('My Wallet')
        ->assertDontSee('Someone Elses');
});

it('creates an account in NPR', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Nabil Bank',
        'type' => 'bank',
        'opening_balance' => '25000.50',
        'color' => '#3B82F6',
    ])->assertRedirect('/accounts');

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Nabil Bank',
        'currency' => 'NPR',
    ]);
});

it('validates account input', function () {
    $this->actingAs(User::factory()->create())
        ->post('/accounts', ['name' => '', 'type' => 'nope', 'opening_balance' => 'abc'])
        ->assertSessionHasErrors(['name', 'type', 'opening_balance']);
});

it('updates an own account', function () {
    $user = User::factory()->create();
    $account = makeAccount($user);

    $this->actingAs($user)->put("/accounts/{$account->id}", [
        'name' => 'Renamed',
        'type' => 'wallet',
        'opening_balance' => '10.00',
        'color' => '#10B981',
    ])->assertRedirect('/accounts');

    expect($account->fresh()->name)->toBe('Renamed');
});

it('forbids touching another users account', function () {
    $account = makeAccount(User::factory()->create());
    $intruder = User::factory()->create();

    $this->actingAs($intruder);

    $this->get("/accounts/{$account->id}/edit")->assertForbidden();
    $this->put("/accounts/{$account->id}", [
        'name' => 'Hacked',
        'type' => 'cash',
        'opening_balance' => '0',
    ])->assertForbidden();
    $this->patch("/accounts/{$account->id}/archive")->assertForbidden();
});

it('archives and restores an account', function () {
    $user = User::factory()->create();
    $account = makeAccount($user);

    $this->actingAs($user)->patch("/accounts/{$account->id}/archive")->assertRedirect('/accounts');
    expect($account->fresh()->is_archived)->toBeTrue();

    $this->patch("/accounts/{$account->id}/archive");
    expect($account->fresh()->is_archived)->toBeFalse();
});

it('calculates account balance from transactions', function () {
    $user = User::factory()->create();
    $main = makeAccount($user, ['name' => 'Main', 'type' => 'bank', 'opening_balance' => 1000]);
    $cash = makeAccount($user, ['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0]);

    $make = fn (array $attrs) => Transaction::create($attrs + [
        'user_id' => $user->id,
        'occurred_on' => now(),
    ]);

    $make(['account_id' => $main->id, 'type' => 'income', 'amount' => 500]);
    $make(['account_id' => $main->id, 'type' => 'expense', 'amount' => 200]);
    $make(['account_id' => $main->id, 'type' => 'transfer', 'amount' => 100, 'transfer_account_id' => $cash->id]);
    $make(['account_id' => $cash->id, 'type' => 'transfer', 'amount' => 50, 'transfer_account_id' => $main->id]);

    expect(Account::withBalance()->find($main->id)->balance)->toBe(1250.0);
    expect(Account::withBalance()->find($cash->id)->balance)->toBe(50.0);
});
