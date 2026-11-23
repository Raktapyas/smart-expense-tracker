<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

function newAccount(User $user, string $name = 'Cash wallet', string $balance = '0'): Account
{
    return $user->accounts()->create([
        'name' => $name,
        'type' => 'cash',
        'opening_balance' => $balance,
    ]);
}

function txPayload(Account $account, array $overrides = []): array
{
    return $overrides + [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => '100.00',
        'occurred_on' => '2026-11-20',
    ];
}

function newTransaction(User $user, Account $account, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-20',
    ]);
}

it('redirects guests away from transactions', function () {
    $this->get('/transactions')->assertRedirect('/login');
});

it('sends users without an account to create one first', function () {
    $this->actingAs(User::factory()->create())
        ->get('/transactions/create')
        ->assertRedirect('/accounts/create');
});

it('records an expense with a category', function () {
    $user = User::factory()->create();
    $account = newAccount($user);
    $category = Category::create(['name' => 'Food & Dining', 'type' => 'expense']);

    $this->actingAs($user)->post('/transactions', txPayload($account, [
        'category_id' => $category->id,
        'merchant' => 'Bhat-Bhateni',
        'amount' => '450.50',
    ]))->assertRedirect('/transactions');

    $transaction = Transaction::first();

    expect($transaction->type->value)->toBe('expense')
        ->and($transaction->amount)->toBe('450.50')
        ->and($transaction->user_id)->toEqual($user->id)
        ->and($transaction->category_id)->toEqual($category->id);
});

it('records a transfer between two accounts and ignores the category', function () {
    $user = User::factory()->create();
    $from = newAccount($user, 'Bank', '1000');
    $to = newAccount($user, 'Cash', '0');
    $category = Category::create(['name' => 'Food', 'type' => 'expense']);

    $this->actingAs($user)->post('/transactions', txPayload($from, [
        'type' => 'transfer',
        'transfer_account_id' => $to->id,
        'category_id' => $category->id,
        'amount' => '300',
    ]))->assertRedirect('/transactions');

    $transaction = Transaction::first();

    expect($transaction->category_id)->toBeNull()
        ->and($transaction->transfer_account_id)->toEqual($to->id);

    expect(Account::withBalance()->find($from->id)->balance)->toBe(700.0);
    expect(Account::withBalance()->find($to->id)->balance)->toBe(300.0);
});

it('rejects a transfer to the same account', function () {
    $user = User::factory()->create();
    $account = newAccount($user);

    $this->actingAs($user)->post('/transactions', txPayload($account, [
        'type' => 'transfer',
        'transfer_account_id' => $account->id,
    ]))->assertSessionHasErrors('transfer_account_id');
});

it('requires a destination account for transfers', function () {
    $user = User::factory()->create();
    $account = newAccount($user);

    $this->actingAs($user)->post('/transactions', txPayload($account, ['type' => 'transfer']))
        ->assertSessionHasErrors('transfer_account_id');
});

it('rejects a category of the wrong type', function () {
    $user = User::factory()->create();
    $account = newAccount($user);
    $income = Category::create(['name' => 'Salary', 'type' => 'income']);

    $this->actingAs($user)->post('/transactions', txPayload($account, ['category_id' => $income->id]))
        ->assertSessionHasErrors('category_id');
});

it('rejects another users account', function () {
    $user = User::factory()->create();
    $theirs = newAccount(User::factory()->create(), 'Theirs');

    $this->actingAs($user)->post('/transactions', txPayload($theirs))
        ->assertSessionHasErrors('account_id');
});

it('rejects zero or negative amounts', function () {
    $user = User::factory()->create();
    $account = newAccount($user);

    $this->actingAs($user)->post('/transactions', txPayload($account, ['amount' => '0']))
        ->assertSessionHasErrors('amount');
    $this->post('/transactions', txPayload($account, ['amount' => '-5']))
        ->assertSessionHasErrors('amount');
});

it('lists only the signed-in user transactions', function () {
    $user = User::factory()->create();
    newTransaction($user, newAccount($user), ['merchant' => 'Mine Cafe']);

    $other = User::factory()->create();
    newTransaction($other, newAccount($other), ['merchant' => 'Their Cafe']);

    $this->actingAs($user)->get('/transactions')
        ->assertOk()
        ->assertSee('Mine Cafe')
        ->assertDontSee('Their Cafe');
});

it('updates an own transaction', function () {
    $user = User::factory()->create();
    $account = newAccount($user);
    $transaction = newTransaction($user, $account);

    $this->actingAs($user)->put("/transactions/{$transaction->id}", txPayload($account, [
        'amount' => '250',
        'merchant' => 'Updated',
    ]))->assertRedirect('/transactions');

    expect($transaction->fresh()->amount)->toBe('250.00')
        ->and($transaction->fresh()->merchant)->toBe('Updated');
});

it('forbids touching another users transaction', function () {
    $owner = User::factory()->create();
    $account = newAccount($owner);
    $transaction = newTransaction($owner, $account);

    $this->actingAs(User::factory()->create());

    $this->get("/transactions/{$transaction->id}/edit")->assertForbidden();
    $this->put("/transactions/{$transaction->id}", txPayload($account))->assertForbidden();
    $this->delete("/transactions/{$transaction->id}")->assertForbidden();
});

it('soft deletes a transaction and restores the balance', function () {
    $user = User::factory()->create();
    $account = newAccount($user, 'Main', '1000');
    $transaction = newTransaction($user, $account, ['amount' => 200]);

    expect(Account::withBalance()->find($account->id)->balance)->toBe(800.0);

    $this->actingAs($user)->delete("/transactions/{$transaction->id}")
        ->assertRedirect('/transactions');

    $this->assertSoftDeleted($transaction);
    expect(Account::withBalance()->find($account->id)->balance)->toBe(1000.0);
});
