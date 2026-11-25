<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create(['name' => 'Main', 'type' => 'bank', 'opening_balance' => 0]);
    Sanctum::actingAs($this->user);
});

function apiTx($test, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $test->user->id,
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

function apiPayload($account, array $overrides = []): array
{
    return $overrides + [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => '100.00',
        'occurred_on' => '2026-11-20',
    ];
}

it('lists this months transactions with totals by default', function () {
    apiTx($this, ['type' => 'income', 'amount' => 5000, 'occurred_on' => '2026-11-02']);
    apiTx($this, ['amount' => 1200.50, 'occurred_on' => '2026-11-05', 'merchant' => 'Tea']);
    apiTx($this, ['occurred_on' => '2025-03-01', 'merchant' => 'Old']);

    $this->getJson('/api/v1/transactions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('totals.range', 'this_month')
        ->assertJsonPath('totals.income', '5000.00')
        ->assertJsonPath('totals.expense', '1200.50')
        ->assertJsonStructure([
            'data' => [[
                'id', 'type', 'amount', 'occurred_on', 'merchant', 'note',
                'account_id', 'transfer_account_id', 'category_id',
                'account' => ['id', 'name'], 'transfer_account', 'category',
                'recurring_transaction_id', 'created_at',
            ]],
            'links',
            'meta',
            'totals',
        ]);

    $this->getJson('/api/v1/transactions?range=all')->assertOk()->assertJsonCount(3, 'data');
});

it('filters, searches and paginates', function () {
    apiTx($this, ['merchant' => 'Himalayan Java']);
    apiTx($this, ['merchant' => 'Pay Day', 'type' => 'income']);
    apiTx($this, ['merchant' => 'Taxi']);

    $this->getJson('/api/v1/transactions?type=income')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/transactions?q=java')->assertOk()->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/transactions?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/transactions?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 100);
});

it('never lists another users transactions', function () {
    $other = User::factory()->create();
    $theirs = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);
    Transaction::create([
        'user_id' => $other->id,
        'account_id' => $theirs->id,
        'type' => 'income',
        'amount' => 9999,
        'occurred_on' => '2026-11-10',
    ]);

    $this->getJson('/api/v1/transactions?range=all')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('totals.income', '0.00');
});

it('creates a transaction and reports auto-categorisation', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $misc = Category::create(['name' => 'Misc', 'type' => 'expense']);
    $this->user->categoryRules()->create(['keyword' => 'bhat', 'category_id' => $groceries->id]);

    $this->postJson('/api/v1/transactions', apiPayload($this->account, [
        'amount' => '450.50',
        'merchant' => 'Bhat-Bhateni',
    ]))->assertCreated()
        ->assertJsonPath('data.amount', '450.50')
        ->assertJsonPath('data.category.name', 'Groceries')
        ->assertJsonPath('meta.auto_categorised', true);

    $this->postJson('/api/v1/transactions', apiPayload($this->account, [
        'merchant' => 'Bhat-Bhateni',
        'category_id' => $misc->id,
    ]))->assertCreated()
        ->assertJsonPath('data.category.name', 'Misc')
        ->assertJsonPath('meta.auto_categorised', false);
});

it('creates a transfer without a category', function () {
    $wallet = $this->user->accounts()->create(['name' => 'Wallet', 'type' => 'cash', 'opening_balance' => 0]);

    $this->postJson('/api/v1/transactions', apiPayload($this->account, [
        'type' => 'transfer',
        'transfer_account_id' => $wallet->id,
        'amount' => '300',
    ]))->assertCreated()
        ->assertJsonPath('data.type', 'transfer')
        ->assertJsonPath('data.transfer_account.name', 'Wallet')
        ->assertJsonPath('data.category', null);
});

it('validates transactions with a JSON error body', function () {
    $this->postJson('/api/v1/transactions', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'account_id', 'amount', 'occurred_on']);

    $theirs = User::factory()->create()->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);

    $this->postJson('/api/v1/transactions', apiPayload($theirs))
        ->assertUnprocessable()->assertJsonValidationErrors('account_id');

    $this->postJson('/api/v1/transactions', apiPayload($this->account, ['amount' => '0']))
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
});

it('shows, updates and deletes an own transaction', function () {
    $transaction = apiTx($this, ['merchant' => 'Tea']);

    $this->getJson("/api/v1/transactions/{$transaction->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $transaction->id)
        ->assertJsonPath('data.account.name', 'Main');

    $this->putJson("/api/v1/transactions/{$transaction->id}", apiPayload($this->account, [
        'amount' => '250',
        'merchant' => 'Updated',
    ]))->assertOk()
        ->assertJsonPath('data.amount', '250.00')
        ->assertJsonPath('data.merchant', 'Updated');

    $this->deleteJson("/api/v1/transactions/{$transaction->id}")->assertNoContent();

    $this->assertSoftDeleted($transaction);
    $this->getJson("/api/v1/transactions/{$transaction->id}")->assertNotFound();
});

it('forbids touching another users transaction', function () {
    $transaction = apiTx($this);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/transactions/{$transaction->id}")->assertForbidden();
    $this->putJson("/api/v1/transactions/{$transaction->id}", apiPayload($this->account))->assertForbidden();
    $this->deleteJson("/api/v1/transactions/{$transaction->id}")->assertForbidden();
});
