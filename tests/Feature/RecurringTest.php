<?php

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringTransactionRunner;

beforeEach(function () {
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create([
        'name' => 'Main',
        'type' => 'bank',
        'opening_balance' => 0,
    ]);
});

function makeRecurring($test, array $attributes = []): RecurringTransaction
{
    $attributes += [
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 5000,
        'merchant' => 'Landlord',
        'frequency' => 'monthly',
        'starts_on' => '2026-11-01',
    ];
    $attributes['next_run_on'] ??= $attributes['starts_on'];

    return $test->user->recurringTransactions()->create($attributes);
}

function runRecurring($test): int
{
    return app(RecurringTransactionRunner::class)->runFor($test->user);
}

function generatedDates(): array
{
    return Transaction::orderBy('occurred_on')->get()
        ->map(fn ($t) => $t->occurred_on->toDateString())
        ->all();
}

it('keeps guests out', function () {
    $this->get('/recurring')->assertRedirect('/login');
});

it('creates a schedule and records a due transaction immediately', function () {
    $this->actingAs($this->user)->post('/recurring', [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '15000',
        'frequency' => 'monthly',
        'starts_on' => '2026-11-24',
        'merchant' => 'Rent',
    ])->assertRedirect('/recurring')
        ->assertSessionHas('status', 'Recurring transaction saved. 1 transaction created.');

    $recurring = RecurringTransaction::first();

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::first()->merchant)->toBe('Rent')
        ->and(Transaction::first()->recurring_transaction_id)->toEqual($recurring->id)
        ->and($recurring->next_run_on->toDateString())->toBe('2026-12-24')
        ->and($recurring->run_count)->toBe(1);
});

it('creates nothing for a schedule that starts in the future', function () {
    $this->actingAs($this->user)->post('/recurring', [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '15000',
        'frequency' => 'monthly',
        'starts_on' => '2026-12-05',
    ])->assertRedirect('/recurring')
        ->assertSessionHas('status', 'Recurring transaction saved.');

    expect(Transaction::count())->toBe(0)
        ->and(RecurringTransaction::first()->next_run_on->toDateString())->toBe('2026-12-05');
});

it('catches up on missed occurrences, clamping to month end', function () {
    $recurring = makeRecurring($this, ['starts_on' => '2026-08-31']);

    expect(runRecurring($this))->toBe(3)
        ->and(generatedDates())->toBe(['2026-08-31', '2026-09-30', '2026-10-31']);

    $fresh = $recurring->fresh();

    expect($fresh->next_run_on->toDateString())->toBe('2026-11-30')
        ->and($fresh->last_run_on->toDateString())->toBe('2026-10-31')
        ->and($fresh->run_count)->toBe(3);
});

it('never creates the same occurrence twice', function () {
    makeRecurring($this);

    expect(runRecurring($this))->toBe(1)
        ->and(runRecurring($this))->toBe(0)
        ->and(Transaction::count())->toBe(1);
});

it('supports weekly and yearly schedules', function () {
    $weekly = makeRecurring($this, ['frequency' => 'weekly', 'starts_on' => '2026-11-10', 'merchant' => 'Weekly']);
    $yearly = makeRecurring($this, ['frequency' => 'yearly', 'starts_on' => '2024-02-29', 'merchant' => 'Yearly']);

    expect(runRecurring($this))->toBe(6)
        ->and($weekly->fresh()->next_run_on->toDateString())->toBe('2026-12-01')
        ->and($yearly->fresh()->next_run_on->toDateString())->toBe('2027-02-28');

    $yearlyDates = Transaction::where('merchant', 'Yearly')->orderBy('occurred_on')->get()
        ->map(fn ($t) => $t->occurred_on->toDateString())
        ->all();

    expect($yearlyDates)->toBe(['2024-02-29', '2025-02-28', '2026-02-28']);
});

it('stops at the end date', function () {
    $recurring = makeRecurring($this, ['starts_on' => '2026-09-15', 'ends_on' => '2026-10-20']);

    expect(runRecurring($this))->toBe(2)
        ->and(generatedDates())->toBe(['2026-09-15', '2026-10-15']);

    $fresh = $recurring->fresh();

    expect($fresh->is_active)->toBeFalse()
        ->and($fresh->statusLabel())->toBe('Ended');
});

it('does not run paused schedules', function () {
    makeRecurring($this, ['is_active' => false]);

    expect(runRecurring($this))->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

it('does not recreate an occurrence the user deleted', function () {
    $recurring = makeRecurring($this);
    runRecurring($this);
    Transaction::first()->delete();

    $recurring->update(['next_run_on' => '2026-11-01', 'run_count' => 0, 'is_active' => true]);

    expect(runRecurring($this))->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

it('creates transfers without a category', function () {
    $wallet = $this->user->accounts()->create(['name' => 'Wallet', 'type' => 'cash', 'opening_balance' => 0]);

    makeRecurring($this, [
        'type' => 'transfer',
        'transfer_account_id' => $wallet->id,
        'amount' => 2000,
        'merchant' => null,
    ]);

    runRecurring($this);

    $transaction = Transaction::first();

    expect($transaction->type->value)->toBe('transfer')
        ->and($transaction->transfer_account_id)->toEqual($wallet->id)
        ->and($transaction->category_id)->toBeNull();
});

it('pauses and resumes without back-filling missed dates', function () {
    $recurring = makeRecurring($this, ['starts_on' => '2026-08-01']);

    $this->actingAs($this->user)->patch("/recurring/{$recurring->id}/toggle")->assertRedirect('/recurring');
    expect($recurring->fresh()->is_active)->toBeFalse();

    $this->patch("/recurring/{$recurring->id}/toggle")->assertRedirect('/recurring');

    $fresh = $recurring->fresh();

    expect($fresh->is_active)->toBeTrue()
        ->and($fresh->next_run_on->toDateString())->toBe('2026-12-01')
        ->and(Transaction::count())->toBe(0)
        ->and(runRecurring($this))->toBe(0);
});

it('updates future details only and ignores frequency and start changes', function () {
    $recurring = makeRecurring($this);
    runRecurring($this);

    $this->actingAs($this->user)->put("/recurring/{$recurring->id}", [
        'account_id' => $this->account->id,
        'amount' => '6000',
        'merchant' => 'Landlord',
        'frequency' => 'weekly',
        'starts_on' => '2020-01-01',
    ])->assertRedirect('/recurring');

    $fresh = $recurring->fresh();

    expect($fresh->amount)->toBe('6000.00')
        ->and($fresh->frequency->value)->toBe('monthly')
        ->and($fresh->starts_on->toDateString())->toBe('2026-11-01')
        ->and(Transaction::first()->amount)->toBe('5000.00');
});

it('validates recurring input', function () {
    $other = User::factory()->create();
    $theirs = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);
    $income = Category::create(['name' => 'Salary', 'type' => 'income']);

    $base = [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '10',
        'frequency' => 'monthly',
        'starts_on' => '2026-11-24',
    ];

    $this->actingAs($this->user);

    $this->post('/recurring', array_merge($base, ['account_id' => $theirs->id]))
        ->assertSessionHasErrors('account_id');
    $this->post('/recurring', array_merge($base, ['type' => 'transfer']))
        ->assertSessionHasErrors('transfer_account_id');
    $this->post('/recurring', array_merge($base, ['category_id' => $income->id]))
        ->assertSessionHasErrors('category_id');
    $this->post('/recurring', array_merge($base, ['ends_on' => '2026-11-01']))
        ->assertSessionHasErrors('ends_on');
    $this->post('/recurring', array_merge($base, ['frequency' => 'daily']))
        ->assertSessionHasErrors('frequency');

    expect(RecurringTransaction::count())->toBe(0);
});

it('forbids touching another users schedule', function () {
    $recurring = makeRecurring($this);

    $this->actingAs(User::factory()->create());

    $this->get("/recurring/{$recurring->id}/edit")->assertForbidden();
    $this->put("/recurring/{$recurring->id}", ['amount' => '1'])->assertForbidden();
    $this->patch("/recurring/{$recurring->id}/toggle")->assertForbidden();
    $this->delete("/recurring/{$recurring->id}")->assertForbidden();
});

it('keeps generated transactions when a schedule is deleted', function () {
    $recurring = makeRecurring($this);
    runRecurring($this);

    $this->actingAs($this->user)->delete("/recurring/{$recurring->id}")->assertRedirect('/recurring');

    expect(RecurringTransaction::count())->toBe(0)
        ->and(Transaction::count())->toBe(1)
        ->and(Transaction::first()->recurring_transaction_id)->toBeNull();
});

it('catches up when the dashboard is opened', function () {
    makeRecurring($this);

    $this->actingAs($this->user)->get('/dashboard')->assertOk();

    expect(Transaction::count())->toBe(1);
});

it('runs from the console command', function () {
    makeRecurring($this);

    $this->artisan('recurring:run')
        ->expectsOutput('Created 1 transaction(s).')
        ->assertSuccessful();
});
