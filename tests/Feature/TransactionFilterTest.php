<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create([
        'name' => 'Main',
        'type' => 'bank',
        'opening_balance' => 0,
    ]);
});

function fTx(User $user, Account $account, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

it('shows only this month by default', function () {
    fTx($this->user, $this->account, ['merchant' => 'November Cafe']);
    fTx($this->user, $this->account, ['merchant' => 'October Cafe', 'occurred_on' => '2026-10-15']);

    $this->actingAs($this->user)->get('/transactions')
        ->assertSee('November Cafe')
        ->assertDontSee('October Cafe');
});

it('includes the last day of the month', function () {
    fTx($this->user, $this->account, ['merchant' => 'Month End Cafe', 'occurred_on' => '2026-11-30']);

    $this->actingAs($this->user)->get('/transactions')->assertSee('Month End Cafe');
});

it('supports last month, last 30 days, this year and all time', function () {
    fTx($this->user, $this->account, ['merchant' => 'Nov Cafe']);
    fTx($this->user, $this->account, ['merchant' => 'Oct Mid Cafe', 'occurred_on' => '2026-10-15']);
    fTx($this->user, $this->account, ['merchant' => 'Oct Late Cafe', 'occurred_on' => '2026-10-27']);
    fTx($this->user, $this->account, ['merchant' => 'Old Cafe', 'occurred_on' => '2025-03-01']);

    $this->actingAs($this->user);

    $this->get('/transactions?range=last_month')
        ->assertSee('Oct Mid Cafe')->assertSee('Oct Late Cafe')->assertDontSee('Nov Cafe');

    $this->get('/transactions?range=last_30_days')
        ->assertSee('Nov Cafe')->assertSee('Oct Late Cafe')->assertDontSee('Oct Mid Cafe');

    $this->get('/transactions?range=this_year')
        ->assertSee('Oct Mid Cafe')->assertDontSee('Old Cafe');

    $this->get('/transactions?range=all')
        ->assertSee('Old Cafe')->assertSee('Nov Cafe');
});

it('filters by type', function () {
    fTx($this->user, $this->account, ['merchant' => 'Coffee Stop']);
    fTx($this->user, $this->account, ['merchant' => 'Pay Day', 'type' => 'income']);

    $this->actingAs($this->user)->get('/transactions?type=income')
        ->assertSee('Pay Day')
        ->assertDontSee('Coffee Stop');
});

it('filters by account including incoming transfers', function () {
    $wallet = $this->user->accounts()->create(['name' => 'Wallet', 'type' => 'cash', 'opening_balance' => 0]);

    fTx($this->user, $this->account, ['merchant' => 'Bank Spend']);
    fTx($this->user, $wallet, ['merchant' => 'Wallet Spend']);
    fTx($this->user, $this->account, [
        'merchant' => 'Move To Wallet',
        'type' => 'transfer',
        'transfer_account_id' => $wallet->id,
    ]);

    $this->actingAs($this->user)->get('/transactions?account_id='.$wallet->id)
        ->assertSee('Wallet Spend')
        ->assertSee('Move To Wallet')
        ->assertDontSee('Bank Spend');
});

it('filters by category', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $fuel = Category::create(['name' => 'Fuel', 'type' => 'expense']);

    fTx($this->user, $this->account, ['merchant' => 'Momo Place', 'category_id' => $food->id]);
    fTx($this->user, $this->account, ['merchant' => 'Petrol Pump', 'category_id' => $fuel->id]);

    $this->actingAs($this->user)->get('/transactions?category_id='.$food->id)
        ->assertSee('Momo Place')
        ->assertDontSee('Petrol Pump');
});

it('searches merchant and note', function () {
    fTx($this->user, $this->account, ['merchant' => 'Himalayan Java']);
    fTx($this->user, $this->account, ['merchant' => 'Gift Shop', 'note' => 'birthday present for Aama']);
    fTx($this->user, $this->account, ['merchant' => 'Unrelated Store']);

    $this->actingAs($this->user);

    $this->get('/transactions?q=java')
        ->assertSee('Himalayan Java')
        ->assertDontSee('Unrelated Store');

    $this->get('/transactions?q=aama')
        ->assertSee('Gift Shop')
        ->assertDontSee('Himalayan Java');
});

it('totals income and spending and ignores transfers and other users', function () {
    $wallet = $this->user->accounts()->create(['name' => 'Wallet', 'type' => 'cash', 'opening_balance' => 0]);

    fTx($this->user, $this->account, ['type' => 'income', 'amount' => 3000]);
    fTx($this->user, $this->account, ['type' => 'income', 'amount' => 2000]);
    fTx($this->user, $this->account, ['amount' => 700.25]);
    fTx($this->user, $this->account, ['amount' => 500.25]);
    fTx($this->user, $this->account, ['type' => 'transfer', 'amount' => 777, 'transfer_account_id' => $wallet->id]);

    $other = User::factory()->create();
    $otherAccount = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);
    fTx($other, $otherAccount, ['type' => 'income', 'amount' => 9999]);

    $this->actingAs($this->user)->get('/transactions')
        ->assertSee('Rs 5,000.00')
        ->assertSee('Rs 1,200.50')
        ->assertSee('Rs 3,799.50')
        ->assertDontSee('9,999.00');
});

it('ignores invalid filter values instead of failing', function () {
    $this->actingAs($this->user)
        ->get('/transactions?range=bogus&type=xyz&account_id[]=1&category_id=abc&q[]=a')
        ->assertOk();
});

it('keeps filters when paging', function () {
    foreach (range(1, 16) as $i) {
        fTx($this->user, $this->account, ['merchant' => "Item {$i}"]);
    }

    $this->actingAs($this->user)->get('/transactions?type=expense')
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertSee('type=expense', false);
});
