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

function insightApiTx($test, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $test->user->id,
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

it('returns insights with screen actions and a category comparison', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);

    insightApiTx($this, ['amount' => 6000, 'category_id' => $food->id]);
    insightApiTx($this, ['amount' => 5000, 'category_id' => $food->id, 'occurred_on' => '2026-10-10']);
    $this->user->budgets()->create(['category_id' => null, 'amount' => 7000]);

    $response = $this->getJson('/api/v1/insights')
        ->assertOk()
        ->assertJsonPath('data.period_label', '1–24 Nov 2026 compared with 1–24 Oct 2026')
        ->assertJsonPath('data.comparison.0.name', 'Food')
        ->assertJsonPath('data.comparison.0.current', '6000.00')
        ->assertJsonPath('data.comparison.0.previous', '5000.00')
        ->assertJsonStructure(['data' => ['insights' => [['tone', 'icon', 'text', 'action']]]]);

    $texts = collect($response->json('data.insights'))->pluck('text');
    $targets = collect($response->json('data.insights'))->pluck('action.target')->filter()->all();

    expect($response->json('data.comparison.0.change'))->toEqual(20)
        ->and($texts->contains(fn ($text) => str_contains($text, '20% more than the same point last month')))->toBeTrue()
        ->and($targets)->toContain('budgets');
});

it('returns empty lists when there is nothing to say', function () {
    $this->getJson('/api/v1/insights')
        ->assertOk()
        ->assertJsonCount(0, 'data.insights')
        ->assertJsonCount(0, 'data.comparison');
});
