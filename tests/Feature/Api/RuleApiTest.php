<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create(['name' => 'Main', 'type' => 'bank', 'opening_balance' => 0]);
    Sanctum::actingAs($this->user);
});

it('creates a rule, lowercases the keyword and categorises existing transactions', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);

    Transaction::create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
        'merchant' => 'Bhat-Bhateni Store',
    ]);

    $this->postJson('/api/v1/rules', [
        'keyword' => ' Bhat ',
        'category_id' => $groceries->id,
        'apply_existing' => true,
    ])->assertCreated()
        ->assertJsonPath('data.keyword', 'bhat')
        ->assertJsonPath('data.category.name', 'Groceries')
        ->assertJsonPath('meta.applied_to_existing', 1);

    expect(Transaction::first()->category_id)->toEqual($groceries->id);
});

it('rejects duplicate keywords and categories the user cannot use', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $private = Category::create(['user_id' => User::factory()->create()->id, 'name' => 'Private', 'type' => 'expense']);
    $this->user->categoryRules()->create(['keyword' => 'bhat', 'category_id' => $groceries->id]);

    $this->postJson('/api/v1/rules', ['keyword' => 'BHAT', 'category_id' => $groceries->id])
        ->assertUnprocessable()->assertJsonValidationErrors('keyword');

    $this->postJson('/api/v1/rules', ['keyword' => 'abc', 'category_id' => $private->id])
        ->assertUnprocessable()->assertJsonValidationErrors('category_id');
});

it('lists only own rules and deletes them', function () {
    $category = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $mine = $this->user->categoryRules()->create(['keyword' => 'my-keyword', 'category_id' => $category->id]);
    User::factory()->create()->categoryRules()->create(['keyword' => 'secret-keyword', 'category_id' => $category->id]);

    $this->getJson('/api/v1/rules')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.keyword', 'my-keyword');

    $this->deleteJson("/api/v1/rules/{$mine->id}")->assertNoContent();

    expect($this->user->categoryRules()->count())->toBe(0);
});

it('forbids deleting another users rule', function () {
    $category = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $theirs = User::factory()->create()->categoryRules()->create(['keyword' => 'keep-me', 'category_id' => $category->id]);

    $this->deleteJson("/api/v1/rules/{$theirs->id}")->assertForbidden();
});

it('suggests a category for a merchant', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $this->user->categoryRules()->create(['keyword' => 'bhat', 'category_id' => $groceries->id]);

    $this->getJson('/api/v1/categorise?merchant=Bhat-Bhateni&type=expense')
        ->assertOk()
        ->assertJson(['category_id' => $groceries->id, 'category_name' => 'Groceries', 'source' => 'rule']);

    $this->getJson('/api/v1/categorise?merchant=Nobody&type=expense')
        ->assertOk()
        ->assertJson(['category_id' => null]);
});
