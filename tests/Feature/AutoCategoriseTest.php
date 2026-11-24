<?php

use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategorySuggester;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create([
        'name' => 'Main',
        'type' => 'bank',
        'opening_balance' => 0,
    ]);
});

function aTx($test, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $test->user->id,
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

function aRule($test, string $keyword, Category $category): CategoryRule
{
    return $test->user->categoryRules()->create(['keyword' => $keyword, 'category_id' => $category->id]);
}

it('keeps guests out', function () {
    $this->get('/rules')->assertRedirect('/login');
    $this->getJson('/categorise')->assertUnauthorized();
});

it('suggests a category from a matching rule, longest keyword first', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $health = Category::create(['name' => 'Health', 'type' => 'expense']);
    aRule($this, 'bhat', $groceries);
    aRule($this, 'bhat-bhateni pharmacy', $health);

    $suggester = app(CategorySuggester::class);
    $a = $suggester->suggest($this->user, 'Bhat-Bhateni Supermarket', 'expense');
    $b = $suggester->suggest($this->user, 'Bhat-Bhateni Pharmacy Thamel', 'expense');

    expect($a->category->name)->toBe('Groceries')
        ->and($a->source)->toBe('rule')
        ->and($b->category->name)->toBe('Health');
});

it('suggests the most used category for a merchant from history', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $transport = Category::create(['name' => 'Transport', 'type' => 'expense']);

    aTx($this, ['merchant' => 'Himalayan Java', 'category_id' => $food->id]);
    aTx($this, ['merchant' => 'himalayan java', 'category_id' => $food->id]);
    aTx($this, ['merchant' => 'Himalayan Java', 'category_id' => $transport->id]);

    $suggestion = app(CategorySuggester::class)->suggest($this->user, 'HIMALAYAN JAVA', 'expense');

    expect($suggestion->category->name)->toBe('Food')
        ->and($suggestion->source)->toBe('history');
});

it('prefers a rule over history', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $fuel = Category::create(['name' => 'Fuel', 'type' => 'expense']);

    aTx($this, ['merchant' => 'Shell Station', 'category_id' => $food->id]);
    aRule($this, 'shell', $fuel);

    $suggestion = app(CategorySuggester::class)->suggest($this->user, 'Shell Station', 'expense');

    expect($suggestion->category->name)->toBe('Fuel')->and($suggestion->source)->toBe('rule');
});

it('returns nothing for unknown merchants, wrong types or other users history', function () {
    $salary = Category::create(['name' => 'Salary', 'type' => 'income']);
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    aRule($this, 'acme', $salary);

    $other = User::factory()->create();
    $otherAccount = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);
    Transaction::create([
        'user_id' => $other->id,
        'account_id' => $otherAccount->id,
        'category_id' => $food->id,
        'type' => 'expense',
        'amount' => 10,
        'occurred_on' => '2026-11-01',
        'merchant' => 'Secret Shop',
    ]);

    $suggester = app(CategorySuggester::class);

    expect($suggester->suggest($this->user, 'Acme Ltd', 'expense'))->toBeNull()
        ->and($suggester->suggest($this->user, 'Secret Shop', 'expense'))->toBeNull()
        ->and($suggester->suggest($this->user, 'Never Seen Before', 'expense'))->toBeNull()
        ->and($suggester->suggest($this->user, '  ', 'expense'))->toBeNull()
        ->and($suggester->suggest($this->user, 'Acme Ltd', 'transfer'))->toBeNull();
});

it('serves suggestions as JSON', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    aRule($this, 'bhat', $groceries);

    $this->actingAs($this->user)->getJson('/categorise?merchant=Bhat-Bhateni&type=expense')
        ->assertOk()
        ->assertJson(['category_id' => $groceries->id, 'category_name' => 'Groceries', 'source' => 'rule']);

    $this->getJson('/categorise?merchant=Nobody&type=expense')
        ->assertOk()
        ->assertJson(['category_id' => null]);

    $this->getJson('/categorise')->assertOk()->assertJson(['category_id' => null]);
});

it('auto-categorises a new transaction that has a merchant but no category', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    aRule($this, 'bhat', $groceries);

    $this->actingAs($this->user)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '250',
        'occurred_on' => '2026-11-20',
        'merchant' => 'Bhat-Bhateni',
    ])->assertRedirect('/transactions')
        ->assertSessionHas('status', 'Transaction added. Auto-categorised as Groceries.');

    expect(Transaction::first()->category_id)->toEqual($groceries->id);
});

it('keeps a category the user chose', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $misc = Category::create(['name' => 'Misc', 'type' => 'expense']);
    aRule($this, 'bhat', $groceries);

    $this->actingAs($this->user)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '250',
        'occurred_on' => '2026-11-20',
        'merchant' => 'Bhat-Bhateni',
        'category_id' => $misc->id,
    ])->assertSessionHas('status', 'Transaction added.');

    expect(Transaction::first()->category_id)->toEqual($misc->id);
});

it('never categorises transfers', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    aRule($this, 'bhat', $groceries);
    $wallet = $this->user->accounts()->create(['name' => 'Wallet', 'type' => 'cash', 'opening_balance' => 0]);

    $this->actingAs($this->user)->post('/transactions', [
        'type' => 'transfer',
        'account_id' => $this->account->id,
        'transfer_account_id' => $wallet->id,
        'amount' => '100',
        'occurred_on' => '2026-11-20',
        'merchant' => 'Bhat-Bhateni',
    ])->assertRedirect('/transactions');

    expect(Transaction::first()->category_id)->toBeNull();
});

it('does not re-categorise when updating a transaction', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    aRule($this, 'bhat', $groceries);
    $transaction = aTx($this, ['merchant' => 'Bhat Shop']);

    $this->actingAs($this->user)->put("/transactions/{$transaction->id}", [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '100',
        'occurred_on' => '2026-11-10',
        'merchant' => 'Bhat Shop',
    ])->assertRedirect('/transactions');

    expect($transaction->fresh()->category_id)->toBeNull();
});

it('creates a rule with a normalised keyword and rejects duplicates', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);

    $this->actingAs($this->user)->post('/rules', ['keyword' => '  Bhat-Bhateni ', 'category_id' => $groceries->id])
        ->assertRedirect('/rules')
        ->assertSessionHas('status', 'Rule saved.');

    $this->assertDatabaseHas('category_rules', [
        'user_id' => $this->user->id,
        'keyword' => 'bhat-bhateni',
        'category_id' => $groceries->id,
    ]);

    $this->post('/rules', ['keyword' => 'BHAT-BHATENI', 'category_id' => $groceries->id])
        ->assertSessionHasErrors('keyword');
});

it('rejects a rule that uses another users category or a tiny keyword', function () {
    $other = User::factory()->create();
    $private = Category::create(['user_id' => $other->id, 'name' => 'Private', 'type' => 'expense']);
    $shared = Category::create(['name' => 'Shared', 'type' => 'expense']);

    $this->actingAs($this->user)->post('/rules', ['keyword' => 'abc', 'category_id' => $private->id])
        ->assertSessionHasErrors('category_id');

    $this->post('/rules', ['keyword' => 'a', 'category_id' => $shared->id])
        ->assertSessionHasErrors('keyword');
});

it('applies a new rule to existing uncategorised transactions of the same type', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $misc = Category::create(['name' => 'Misc', 'type' => 'expense']);

    $match = aTx($this, ['merchant' => 'Bhat-Bhateni Store']);
    $already = aTx($this, ['merchant' => 'Bhat-Bhateni Store', 'category_id' => $misc->id]);
    $unrelated = aTx($this, ['merchant' => 'Random Place']);
    $income = aTx($this, ['merchant' => 'Bhat-Bhateni Salary', 'type' => 'income']);

    $other = User::factory()->create();
    $otherAccount = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);
    $theirs = Transaction::create([
        'user_id' => $other->id,
        'account_id' => $otherAccount->id,
        'type' => 'expense',
        'amount' => 5,
        'occurred_on' => '2026-11-01',
        'merchant' => 'Bhat-Bhateni Store',
    ]);

    $this->actingAs($this->user)->post('/rules', [
        'keyword' => 'Bhat-Bhateni',
        'category_id' => $groceries->id,
        'apply_existing' => '1',
    ])->assertRedirect('/rules')
        ->assertSessionHas('status', 'Rule saved. 1 existing transaction categorised.');

    expect($match->fresh()->category_id)->toEqual($groceries->id)
        ->and($already->fresh()->category_id)->toEqual($misc->id)
        ->and($unrelated->fresh()->category_id)->toBeNull()
        ->and($income->fresh()->category_id)->toBeNull()
        ->and($theirs->fresh()->category_id)->toBeNull();
});

it('lists only the signed-in user rules and lets them delete their own', function () {
    $category = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $mine = aRule($this, 'my-keyword', $category);

    $other = User::factory()->create();
    $other->categoryRules()->create(['keyword' => 'secret-keyword', 'category_id' => $category->id]);

    $this->actingAs($this->user)->get('/rules')
        ->assertOk()
        ->assertSee('my-keyword')
        ->assertDontSee('secret-keyword');

    $this->delete("/rules/{$mine->id}")->assertRedirect('/rules');
    expect(CategoryRule::find($mine->id))->toBeNull();
});

it('forbids deleting another users rule', function () {
    $category = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $rule = aRule($this, 'keep-me', $category);

    $this->actingAs(User::factory()->create())->delete("/rules/{$rule->id}")->assertForbidden();
});
