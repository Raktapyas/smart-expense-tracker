<?php

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create(['name' => 'Main', 'type' => 'bank', 'opening_balance' => 0]);
    Sanctum::actingAs($this->user);
});

function recurRow($test, array $attributes = []): RecurringTransaction
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

it('creates a schedule and records anything already due', function () {
    $this->postJson('/api/v1/recurring', [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '15000',
        'frequency' => 'monthly',
        'starts_on' => '2026-11-24',
        'merchant' => 'Rent',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'Active')
        ->assertJsonPath('data.frequency_label', 'Monthly')
        ->assertJsonPath('data.amount', '15000.00')
        ->assertJsonPath('data.next_run_on', '2026-12-24')
        ->assertJsonPath('meta.transactions_created', 1);

    expect(Transaction::count())->toBe(1);
});

it('creates a future schedule without creating transactions', function () {
    $this->postJson('/api/v1/recurring', [
        'type' => 'income',
        'account_id' => $this->account->id,
        'amount' => '80000',
        'frequency' => 'monthly',
        'starts_on' => '2026-12-05',
        'merchant' => 'Salary',
    ])->assertCreated()
        ->assertJsonPath('data.next_run_on', '2026-12-05')
        ->assertJsonPath('meta.transactions_created', 0);

    expect(Transaction::count())->toBe(0);
});

it('validates schedules', function () {
    $theirs = User::factory()->create()->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);
    $salary = Category::create(['name' => 'Salary', 'type' => 'income']);

    $base = [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '10',
        'frequency' => 'monthly',
        'starts_on' => '2026-12-01',
    ];

    $this->postJson('/api/v1/recurring', array_merge($base, ['account_id' => $theirs->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('account_id');
    $this->postJson('/api/v1/recurring', array_merge($base, ['type' => 'transfer']))
        ->assertUnprocessable()->assertJsonValidationErrors('transfer_account_id');
    $this->postJson('/api/v1/recurring', array_merge($base, ['category_id' => $salary->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('category_id');
    $this->postJson('/api/v1/recurring', array_merge($base, ['ends_on' => '2026-11-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('ends_on');
    $this->postJson('/api/v1/recurring', array_merge($base, ['frequency' => 'daily']))
        ->assertUnprocessable()->assertJsonValidationErrors('frequency');
});

it('lists only own schedules, active first', function () {
    recurRow($this, ['merchant' => 'Paused one', 'is_active' => false]);
    recurRow($this, ['merchant' => 'Active one']);
    User::factory()->create()->recurringTransactions()->create([
        'account_id' => $this->user->accounts()->first()->id,
        'type' => 'expense',
        'amount' => 1,
        'merchant' => 'Secret',
        'frequency' => 'monthly',
        'starts_on' => '2026-12-01',
        'next_run_on' => '2026-12-01',
    ]);

    $this->getJson('/api/v1/recurring')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.merchant', 'Active one')
        ->assertJsonPath('data.1.status', 'Paused');
});

it('updates future details only', function () {
    $recurring = recurRow($this);

    $this->putJson("/api/v1/recurring/{$recurring->id}", [
        'account_id' => $this->account->id,
        'amount' => '6000',
        'merchant' => 'Landlord',
        'frequency' => 'weekly',
        'starts_on' => '2020-01-01',
    ])->assertOk()
        ->assertJsonPath('data.amount', '6000.00')
        ->assertJsonPath('data.frequency', 'monthly')
        ->assertJsonPath('data.starts_on', '2026-11-01');
});

it('pauses and resumes without back-filling', function () {
    $recurring = recurRow($this, ['starts_on' => '2026-08-01']);

    $this->patchJson("/api/v1/recurring/{$recurring->id}/toggle")
        ->assertOk()->assertJsonPath('data.status', 'Paused');

    $this->patchJson("/api/v1/recurring/{$recurring->id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.status', 'Active')
        ->assertJsonPath('data.next_run_on', '2026-12-01');

    expect(Transaction::count())->toBe(0);
});

it('refuses to resume an ended schedule', function () {
    $recurring = recurRow($this, [
        'starts_on' => '2026-09-15',
        'ends_on' => '2026-10-20',
        'next_run_on' => '2026-11-15',
        'run_count' => 2,
        'is_active' => false,
    ]);

    $this->patchJson("/api/v1/recurring/{$recurring->id}/toggle")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_on');
});

it('keeps generated transactions when a schedule is deleted', function () {
    $recurring = recurRow($this);

    $this->getJson('/api/v1/dashboard')->assertOk();

    $this->deleteJson("/api/v1/recurring/{$recurring->id}")->assertNoContent();

    expect(RecurringTransaction::count())->toBe(0)
        ->and(Transaction::count())->toBe(1)
        ->and(Transaction::first()->recurring_transaction_id)->toBeNull();
});

it('forbids touching another users schedule', function () {
    $recurring = recurRow($this);

    Sanctum::actingAs(User::factory()->create());

    $this->putJson("/api/v1/recurring/{$recurring->id}", ['amount' => '1'])->assertForbidden();
    $this->patchJson("/api/v1/recurring/{$recurring->id}/toggle")->assertForbidden();
    $this->deleteJson("/api/v1/recurring/{$recurring->id}")->assertForbidden();
});
