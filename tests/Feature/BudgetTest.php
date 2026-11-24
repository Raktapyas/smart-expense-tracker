<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;

beforeEach(function () {
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create([
        'name' => 'Main',
        'type' => 'bank',
        'opening_balance' => 0,
    ]);
});

function bTx($test, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $test->user->id,
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

it('redirects guests away from budgets', function () {
    $this->get('/budgets')->assertRedirect('/login');
});

it('creates an overall budget', function () {
    $this->actingAs($this->user)->post('/budgets', ['category_id' => '', 'amount' => '50000'])
        ->assertRedirect('/budgets');

    $this->assertDatabaseHas('budgets', ['user_id' => $this->user->id, 'category_id' => null]);
});

it('creates a category budget', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);

    $this->actingAs($this->user)->post('/budgets', ['category_id' => $food->id, 'amount' => '12000.50'])
        ->assertRedirect('/budgets');

    $this->assertDatabaseHas('budgets', ['user_id' => $this->user->id, 'category_id' => $food->id]);
});

it('rejects an income category', function () {
    $salary = Category::create(['name' => 'Salary', 'type' => 'income']);

    $this->actingAs($this->user)->post('/budgets', ['category_id' => $salary->id, 'amount' => '1000'])
        ->assertSessionHasErrors('category_id');
});

it('rejects a duplicate category budget', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $this->user->budgets()->create(['category_id' => $food->id, 'amount' => 1000]);

    $this->actingAs($this->user)->post('/budgets', ['category_id' => $food->id, 'amount' => '2000'])
        ->assertSessionHasErrors('category_id');
});

it('rejects a second overall budget', function () {
    $this->user->budgets()->create(['category_id' => null, 'amount' => 1000]);

    $this->actingAs($this->user)->post('/budgets', ['category_id' => '', 'amount' => '2000'])
        ->assertSessionHasErrors('category_id');
});

it('rejects an invalid amount', function () {
    $this->actingAs($this->user)->post('/budgets', ['category_id' => '', 'amount' => '0'])
        ->assertSessionHasErrors('amount');
});

it('lists only the signed-in user budgets', function () {
    $this->user->budgets()->create(['category_id' => null, 'amount' => 11111]);

    $other = User::factory()->create();
    $other->budgets()->create(['category_id' => null, 'amount' => 77777]);

    $this->actingAs($this->user)->get('/budgets')
        ->assertOk()
        ->assertSee('11,111.00')
        ->assertDontSee('77,777.00');
});

it('updates and deletes an own budget', function () {
    $budget = $this->user->budgets()->create(['category_id' => null, 'amount' => 1000]);

    $this->actingAs($this->user)->put("/budgets/{$budget->id}", ['category_id' => '', 'amount' => '2500'])
        ->assertRedirect('/budgets');
    expect($budget->fresh()->amount)->toBe('2500.00');

    $this->delete("/budgets/{$budget->id}")->assertRedirect('/budgets');
    expect(Budget::find($budget->id))->toBeNull();
});

it('forbids touching another users budget', function () {
    $budget = $this->user->budgets()->create(['category_id' => null, 'amount' => 1000]);

    $this->actingAs(User::factory()->create());

    $this->get("/budgets/{$budget->id}/edit")->assertForbidden();
    $this->put("/budgets/{$budget->id}", ['category_id' => '', 'amount' => '5'])->assertForbidden();
    $this->delete("/budgets/{$budget->id}")->assertForbidden();
});

it('calculates progress from this months expenses only', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);

    $this->user->budgets()->create(['category_id' => $food->id, 'amount' => 1000]);
    $this->user->budgets()->create(['category_id' => null, 'amount' => 1200]);

    bTx($this, ['category_id' => $food->id, 'amount' => 850, 'occurred_on' => '2026-11-03']);
    bTx($this, ['amount' => 400, 'occurred_on' => '2026-11-10']);
    bTx($this, ['category_id' => $food->id, 'amount' => 999, 'occurred_on' => '2026-10-30']);
    bTx($this, ['type' => 'income', 'amount' => 7000, 'occurred_on' => '2026-11-02']);

    $progress = app(BudgetService::class)->progress($this->user);

    expect($progress)->toHaveCount(2)
        ->and($progress[0]['name'])->toBe('Overall monthly budget')
        ->and($progress[0]['spent'])->toBe(1250.0)
        ->and($progress[0]['status'])->toBe('over')
        ->and($progress[0]['remaining'])->toBe(0.0)
        ->and($progress[1]['name'])->toBe('Food')
        ->and($progress[1]['spent'])->toBe(850.0)
        ->and($progress[1]['percent'])->toBe(85.0)
        ->and($progress[1]['status'])->toBe('warning')
        ->and($progress[1]['remaining'])->toBe(150.0);
});

it('shows budgets and the over budget count on the dashboard', function () {
    $this->user->budgets()->create(['category_id' => null, 'amount' => 100]);
    bTx($this, ['amount' => 500]);

    $this->actingAs($this->user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Overall monthly budget')
        ->assertSee('1 over budget');
});
