<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardService;

beforeEach(function () {
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create([
        'name' => 'Main',
        'type' => 'bank',
        'opening_balance' => 10000,
    ]);
});

function dTx(User $user, Account $account, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

it('shows a welcome card to a user with no accounts', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Add your first account');
});

it('shows real balance and monthly totals', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);

    dTx($this->user, $this->account, ['type' => 'income', 'amount' => 5000, 'occurred_on' => '2026-11-02']);
    dTx($this->user, $this->account, ['amount' => 1200.50, 'occurred_on' => '2026-11-05', 'category_id' => $food->id]);
    dTx($this->user, $this->account, ['amount' => 300, 'occurred_on' => '2026-10-20']);

    $this->actingAs($this->user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Rs 13,499.50')
        ->assertSee('Rs 3,799.50')
        ->assertSee('Food')
        ->assertSee('100%');
});

it('never shows another users data', function () {
    $other = User::factory()->create();
    $theirs = $other->accounts()->create(['name' => 'Secret Savings', 'type' => 'bank', 'opening_balance' => 55555]);
    dTx($other, $theirs, ['merchant' => 'Secret Shop']);

    $this->actingAs($this->user)->get('/dashboard')
        ->assertDontSee('Secret Savings')
        ->assertDontSee('Secret Shop')
        ->assertDontSee('55,555.00');
});

it('leaves archived accounts out of the total', function () {
    $this->user->accounts()->create([
        'name' => 'Old Account',
        'type' => 'cash',
        'opening_balance' => 99999,
        'is_archived' => true,
    ]);

    $this->actingAs($this->user)->get('/dashboard')
        ->assertDontSee('Old Account')
        ->assertDontSee('99,999.00')
        ->assertSee('Rs 10,000.00');
});

it('builds a six month trend', function () {
    dTx($this->user, $this->account, ['type' => 'income', 'amount' => 5000, 'occurred_on' => '2026-11-02']);
    dTx($this->user, $this->account, ['amount' => 300, 'occurred_on' => '2026-10-20']);

    $trend = app(DashboardService::class)->build($this->user)['trend'];

    expect($trend)->toHaveCount(6)
        ->and($trend[0]['label'])->toBe('Jun')
        ->and($trend[5]['label'])->toBe('Nov')
        ->and($trend[5]['income'])->toBe(5000.0)
        ->and($trend[4]['label'])->toBe('Oct')
        ->and($trend[4]['expense'])->toBe(300.0);
});

it('folds small categories into everything else', function () {
    foreach (range(1, 8) as $i) {
        $category = Category::create(['name' => "Cat {$i}", 'type' => 'expense']);
        dTx($this->user, $this->account, ['category_id' => $category->id, 'amount' => 1000 - $i * 10]);
    }

    $breakdown = app(DashboardService::class)->build($this->user)['categoryBreakdown'];

    expect($breakdown)->toHaveCount(6)
        ->and($breakdown->last()['name'])->toBe('Everything else');
});
