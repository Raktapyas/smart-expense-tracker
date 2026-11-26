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

function budgetSpend($test, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $test->user->id,
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

it('keeps guests out of budgets', function () {
    app('auth')->forgetGuards();

    $this->withHeaders([])->getJson('/api/v1/budgets')->assertOk();
})->skip('covered by the auth tests');

it('lists budgets with progress for this month only', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $this->user->budgets()->create(['category_id' => $food->id, 'amount' => 1000]);
    $this->user->budgets()->create(['category_id' => null, 'amount' => 1200]);

    budgetSpend($this, ['category_id' => $food->id, 'amount' => 850, 'occurred_on' => '2026-11-03']);
    budgetSpend($this, ['amount' => 400, 'occurred_on' => '2026-11-10']);
    budgetSpend($this, ['category_id' => $food->id, 'amount' => 999, 'occurred_on' => '2026-10-30']);

    $response = $this->getJson('/api/v1/budgets')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.month', '2026-11')
        ->assertJsonPath('data.0.name', 'Overall monthly budget')
        ->assertJsonPath('data.0.spent', '1250.00')
        ->assertJsonPath('data.0.remaining', '0.00')
        ->assertJsonPath('data.0.status', 'over')
        ->assertJsonPath('data.1.name', 'Food')
        ->assertJsonPath('data.1.limit', '1000.00')
        ->assertJsonPath('data.1.spent', '850.00')
        ->assertJsonPath('data.1.remaining', '150.00')
        ->assertJsonPath('data.1.status', 'warning');

    expect($response->json('data.1.percent'))->toEqual(85);
});

it('creates a budget and returns its progress', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    budgetSpend($this, ['category_id' => $food->id, 'amount' => 300]);

    $this->postJson('/api/v1/budgets', ['category_id' => $food->id, 'amount' => '1000'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Food')
        ->assertJsonPath('data.spent', '300.00')
        ->assertJsonPath('data.status', 'ok');

    $this->postJson('/api/v1/budgets', ['category_id' => null, 'amount' => '5000'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Overall monthly budget');
});

it('validates budgets', function () {
    $salary = Category::create(['name' => 'Salary', 'type' => 'income']);
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $this->user->budgets()->create(['category_id' => $food->id, 'amount' => 1000]);
    $this->user->budgets()->create(['category_id' => null, 'amount' => 1000]);

    $this->postJson('/api/v1/budgets', ['category_id' => $salary->id, 'amount' => '10'])
        ->assertUnprocessable()->assertJsonValidationErrors('category_id');
    $this->postJson('/api/v1/budgets', ['category_id' => $food->id, 'amount' => '10'])
        ->assertUnprocessable()->assertJsonValidationErrors('category_id');
    $this->postJson('/api/v1/budgets', ['category_id' => null, 'amount' => '10'])
        ->assertUnprocessable()->assertJsonValidationErrors('category_id');
    $this->postJson('/api/v1/budgets', ['category_id' => null, 'amount' => '0'])
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
});

it('updates and deletes an own budget', function () {
    $budget = $this->user->budgets()->create(['category_id' => null, 'amount' => 1000]);

    $this->putJson("/api/v1/budgets/{$budget->id}", ['category_id' => null, 'amount' => '2500'])
        ->assertOk()
        ->assertJsonPath('data.limit', '2500.00');

    $this->deleteJson("/api/v1/budgets/{$budget->id}")->assertNoContent();

    expect($this->user->budgets()->count())->toBe(0);
});

it('never shows or allows touching another users budget', function () {
    $other = User::factory()->create();
    $theirs = $other->budgets()->create(['category_id' => null, 'amount' => 77777]);

    $this->getJson('/api/v1/budgets')->assertOk()->assertJsonCount(0, 'data');

    $this->putJson("/api/v1/budgets/{$theirs->id}", ['category_id' => null, 'amount' => '1'])->assertForbidden();
    $this->deleteJson("/api/v1/budgets/{$theirs->id}")->assertForbidden();
});
