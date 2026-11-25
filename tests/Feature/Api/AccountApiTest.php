<?php

use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('lists accounts with calculated balances and a total of active accounts', function () {
    $main = $this->user->accounts()->create(['name' => 'Main', 'type' => 'bank', 'opening_balance' => 1000]);
    $this->user->accounts()->create(['name' => 'Old', 'type' => 'cash', 'opening_balance' => 777, 'is_archived' => true]);

    Transaction::create([
        'user_id' => $this->user->id,
        'account_id' => $main->id,
        'type' => 'income',
        'amount' => 500,
        'occurred_on' => '2026-11-02',
    ]);

    $this->getJson('/api/v1/accounts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Main')
        ->assertJsonPath('data.0.balance', '1500.00')
        ->assertJsonPath('data.0.currency', 'NPR')
        ->assertJsonPath('data.1.is_archived', true)
        ->assertJsonPath('meta.total_balance', '1500.00');
});

it('never lists another users accounts', function () {
    User::factory()->create()->accounts()->create(['name' => 'Secret', 'type' => 'bank', 'opening_balance' => 1]);

    $this->getJson('/api/v1/accounts')->assertOk()->assertJsonCount(0, 'data');
});

it('creates an account in NPR', function () {
    $this->postJson('/api/v1/accounts', [
        'name' => 'Nabil Bank',
        'type' => 'bank',
        'opening_balance' => '25000.50',
        'color' => '#3B82F6',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Nabil Bank')
        ->assertJsonPath('data.currency', 'NPR')
        ->assertJsonPath('data.balance', '25000.50');
});

it('validates account input with a JSON error body', function () {
    $this->postJson('/api/v1/accounts', ['name' => '', 'type' => 'nope', 'opening_balance' => 'abc'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'type', 'opening_balance']);
});

it('updates and archives an own account', function () {
    $account = $this->user->accounts()->create(['name' => 'Wallet', 'type' => 'cash', 'opening_balance' => 0]);

    $this->putJson("/api/v1/accounts/{$account->id}", [
        'name' => 'Renamed',
        'type' => 'wallet',
        'opening_balance' => '10.00',
    ])->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.type', 'wallet');

    $this->patchJson("/api/v1/accounts/{$account->id}/archive")->assertOk()->assertJsonPath('data.is_archived', true);
    $this->patchJson("/api/v1/accounts/{$account->id}/archive")->assertOk()->assertJsonPath('data.is_archived', false);
});

it('forbids touching another users account', function () {
    $account = User::factory()->create()->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);

    $this->putJson("/api/v1/accounts/{$account->id}", [
        'name' => 'Hacked',
        'type' => 'cash',
        'opening_balance' => '0',
    ])->assertForbidden();

    $this->patchJson("/api/v1/accounts/{$account->id}/archive")->assertForbidden();
});
