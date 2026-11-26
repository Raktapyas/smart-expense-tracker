<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create(['name' => 'Main', 'type' => 'bank', 'opening_balance' => 10000]);
    Sanctum::actingAs($this->user);
});

function dashTx($test, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $test->user->id,
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

it('returns the whole home screen in one response', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);

    dashTx($this, ['type' => 'income', 'amount' => 5000, 'occurred_on' => '2026-11-02']);
    dashTx($this, ['amount' => 1200.50, 'occurred_on' => '2026-11-05', 'category_id' => $food->id]);
    dashTx($this, ['amount' => 300, 'occurred_on' => '2026-10-20']);

    $response = $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.month_label', 'November 2026')
        ->assertJsonPath('data.total_balance', '13499.50')
        ->assertJsonPath('data.income', '5000.00')
        ->assertJsonPath('data.expense', '1200.50')
        ->assertJsonPath('data.saved', '3799.50')
        ->assertJsonPath('data.accounts.0.balance', '13499.50')
        ->assertJsonPath('data.category_breakdown.0.name', 'Food')
        ->assertJsonPath('data.category_breakdown.0.total', '1200.50')
        ->assertJsonCount(6, 'data.trend')
        ->assertJsonCount(3, 'data.recent')
        ->assertJsonPath('data.over_budget_count', 0)
        ->assertJsonStructure(['data' => [
            'month_label', 'total_balance', 'income', 'expense', 'saved', 'accounts',
            'category_breakdown', 'trend', 'recent', 'budgets', 'over_budget_count', 'insights',
        ]]);

    expect($response->json('data.category_breakdown.0.percent'))->toEqual(100)
        ->and($response->json('data.trend.5.label'))->toBe('Nov')
        ->and($response->json('data.trend.5.income'))->toBe('5000.00');
});

it('creates due recurring transactions when the dashboard is requested', function () {
    $this->user->recurringTransactions()->create([
        'account_id' => $this->account->id,
        'type' => 'expense',
        'amount' => 5000,
        'merchant' => 'Rent',
        'frequency' => 'monthly',
        'starts_on' => '2026-11-01',
        'next_run_on' => '2026-11-01',
    ]);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.expense', '5000.00')
        ->assertJsonPath('data.recent.0.merchant', 'Rent');

    expect(Transaction::count())->toBe(1);
});

it('never includes another users data', function () {
    $other = User::factory()->create();
    $theirs = $other->accounts()->create(['name' => 'Secret Savings', 'type' => 'bank', 'opening_balance' => 55555]);
    Transaction::create([
        'user_id' => $other->id,
        'account_id' => $theirs->id,
        'type' => 'expense',
        'amount' => 9000,
        'merchant' => 'Secret Shop',
        'occurred_on' => '2026-11-10',
    ]);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.total_balance', '10000.00')
        ->assertJsonCount(1, 'data.accounts')
        ->assertJsonCount(0, 'data.recent');
});
