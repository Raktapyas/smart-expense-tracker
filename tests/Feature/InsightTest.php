<?php

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InsightService;

beforeEach(function () {
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create([
        'name' => 'Main',
        'type' => 'bank',
        'opening_balance' => 0,
    ]);
});

function iTx($test, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $test->user->id,
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

function insightTexts(User $user): array
{
    return app(InsightService::class)->report($user)['insights']->pluck('text')->all();
}

function hasText(array $texts, string $needle): bool
{
    return collect($texts)->contains(fn ($text) => str_contains($text, $needle));
}

it('describes spending as more, less or about the same', function (int $now, int $before, string $needle) {
    iTx($this, ['amount' => $now, 'occurred_on' => '2026-11-10']);
    iTx($this, ['amount' => $before, 'occurred_on' => '2026-10-10']);
    iTx($this, ['amount' => 9999, 'occurred_on' => '2026-10-30']); // after the cutoff day: must not count

    $texts = insightTexts($this->user);

    expect(hasText($texts, $needle))->toBeTrue()
        ->and(hasText($texts, '9,999'))->toBeFalse();
})->with([
    'more' => [6000, 5000, '20% more than the same point last month (Rs 5,000.00)'],
    'less' => [4000, 5000, '20% less than the same point last month (Rs 5,000.00)'],
    'same' => [5100, 5000, 'about the same as this point last month'],
]);

it('skips the comparison when there was no spending last month', function () {
    iTx($this, ['amount' => 6000]);

    expect(hasText(insightTexts($this->user), 'last month'))->toBeFalse();
});

it('names the biggest category', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $transport = Category::create(['name' => 'Transport', 'type' => 'expense']);

    iTx($this, ['amount' => 6000, 'category_id' => $food->id]);
    iTx($this, ['amount' => 1000, 'category_id' => $transport->id]);

    expect(hasText(insightTexts($this->user), 'Food is your biggest category: Rs 6,000.00 (86% of spending).'))->toBeTrue();
});

it('flags categories that moved a lot and ignores small changes', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $transport = Category::create(['name' => 'Transport', 'type' => 'expense']);
    $tea = Category::create(['name' => 'Tea', 'type' => 'expense']);

    iTx($this, ['amount' => 4000, 'category_id' => $food->id]);
    iTx($this, ['amount' => 1000, 'category_id' => $transport->id]);
    iTx($this, ['amount' => 1000, 'category_id' => $tea->id]);
    iTx($this, ['amount' => 2000, 'category_id' => $food->id, 'occurred_on' => '2026-10-12']);
    iTx($this, ['amount' => 3000, 'category_id' => $transport->id, 'occurred_on' => '2026-10-13']);
    iTx($this, ['amount' => 900, 'category_id' => $tea->id, 'occurred_on' => '2026-10-14']);

    $texts = insightTexts($this->user);

    expect(hasText($texts, 'Food spending is up 100% compared with this point last month (Rs 2,000.00 → Rs 4,000.00).'))->toBeTrue()
        ->and(hasText($texts, 'You cut Transport spending by 67% compared with this point last month (Rs 3,000.00 → Rs 1,000.00).'))->toBeTrue()
        ->and(hasText($texts, 'Tea'))->toBeFalse();
});

it('projects month-end spending and compares it with the overall budget', function () {
    iTx($this, ['amount' => 6000]);

    expect(hasText(insightTexts($this->user), "At this pace you'll spend about Rs 7,500.00 this month."))->toBeTrue();

    $budget = $this->user->budgets()->create(['category_id' => null, 'amount' => 7000]);

    expect(hasText(insightTexts($this->user), 'about Rs 7,500.00 this month, which is Rs 500.00 over your Rs 7,000.00 monthly budget.'))->toBeTrue();

    $budget->update(['amount' => 9000]);

    expect(hasText(insightTexts($this->user), 'within your Rs 9,000.00 monthly budget.'))->toBeTrue();
});

it('does not project spending early in the month', function () {
    $this->travelTo('2026-11-03 12:00:00');
    iTx($this, ['amount' => 5000, 'occurred_on' => '2026-11-02']);

    expect(hasText(insightTexts($this->user), 'At this pace'))->toBeFalse();
});

it('reports the savings rate or overspending', function () {
    iTx($this, ['type' => 'income', 'amount' => 10000, 'occurred_on' => '2026-11-02']);
    iTx($this, ['amount' => 6000]);

    expect(hasText(insightTexts($this->user), "You've saved 40% of your income this month."))->toBeTrue();

    iTx($this, ['amount' => 9000]);

    expect(hasText(insightTexts($this->user), "You've spent Rs 5,000.00 more than you earned this month."))->toBeTrue();
});

it('names the largest expense once there are at least three', function () {
    iTx($this, ['amount' => 100, 'occurred_on' => '2026-11-03']);
    iTx($this, ['amount' => 200, 'occurred_on' => '2026-11-04']);

    expect(hasText(insightTexts($this->user), 'largest expense'))->toBeFalse();

    iTx($this, ['amount' => 5000, 'occurred_on' => '2026-11-12', 'merchant' => 'Bhat-Bhateni']);

    expect(hasText(insightTexts($this->user), 'Your largest expense this month was Rs 5,000.00 at Bhat-Bhateni on 12 Nov.'))->toBeTrue();
});

it('nudges towards a smart rule when three or more expenses have no category', function () {
    iTx($this, ['amount' => 100, 'occurred_on' => '2026-11-03']);
    iTx($this, ['amount' => 100, 'occurred_on' => '2026-11-04']);

    expect(hasText(insightTexts($this->user), 'have no category'))->toBeFalse();

    iTx($this, ['amount' => 100, 'occurred_on' => '2026-11-05']);

    expect(hasText(insightTexts($this->user), '3 expenses this month have no category.'))->toBeTrue();
});

it('lists recurring payments due in the next seven days', function () {
    $recurring = fn (array $attributes) => RecurringTransaction::create($attributes + [
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'type' => 'expense',
        'frequency' => 'monthly',
    ]);

    $recurring(['merchant' => 'Rent', 'amount' => 15000, 'starts_on' => '2026-12-01', 'next_run_on' => '2026-12-01']);
    $recurring(['merchant' => 'Salary', 'type' => 'income', 'amount' => 80000, 'starts_on' => '2026-11-25', 'next_run_on' => '2026-11-25']);
    $recurring(['merchant' => 'Far Away', 'amount' => 1, 'starts_on' => '2026-12-05', 'next_run_on' => '2026-12-05']);
    $recurring(['merchant' => 'Paused', 'amount' => 1, 'starts_on' => '2026-11-26', 'next_run_on' => '2026-11-26', 'is_active' => false]);

    $texts = insightTexts($this->user);

    expect(hasText($texts, 'Rent: Rs 15,000.00 is due on 1 Dec (in 7 days).'))->toBeTrue()
        ->and(hasText($texts, 'Salary: Rs 80,000.00 is expected on 25 Nov (tomorrow).'))->toBeTrue()
        ->and(hasText($texts, 'Far Away'))->toBeFalse()
        ->and(hasText($texts, 'Paused'))->toBeFalse();
});

it('never uses another users data', function () {
    $other = User::factory()->create();
    $otherAccount = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);

    foreach (['2026-11-03', '2026-11-04', '2026-11-05'] as $date) {
        Transaction::create([
            'user_id' => $other->id,
            'account_id' => $otherAccount->id,
            'type' => 'expense',
            'amount' => 9000,
            'occurred_on' => $date,
        ]);
    }

    expect(insightTexts($this->user))->toBe([]);
});

it('keeps guests out of the insights page', function () {
    $this->get('/insights')->assertRedirect('/login');
});

it('shows the insights page with a category comparison table', function () {
    $food = Category::create(['name' => 'Food', 'type' => 'expense']);
    $transport = Category::create(['name' => 'Transport', 'type' => 'expense']);

    iTx($this, ['amount' => 4000, 'category_id' => $food->id]);
    iTx($this, ['amount' => 1000, 'category_id' => $transport->id]);
    iTx($this, ['amount' => 2000, 'category_id' => $food->id, 'occurred_on' => '2026-10-12']);
    iTx($this, ['amount' => 3000, 'category_id' => $transport->id, 'occurred_on' => '2026-10-13']);

    $this->actingAs($this->user)->get('/insights')
        ->assertOk()
        ->assertSee('1–24 Nov 2026 compared with 1–24 Oct 2026')
        ->assertSee('Food')
        ->assertSee('+100%')
        ->assertSee('-67%');
});

it('shows an insights card on the dashboard only when there is something to say', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertDontSee('See all');

    iTx($this, ['amount' => 6000]);
    iTx($this, ['amount' => 5000, 'occurred_on' => '2026-10-10']);

    $this->get('/dashboard')->assertOk()->assertSee('See all');
});
