<?php

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

function exportTx($test, array $attributes = []): Transaction
{
    return Transaction::create($attributes + [
        'user_id' => $test->user->id,
        'account_id' => $test->account->id,
        'type' => 'expense',
        'amount' => 100,
        'occurred_on' => '2026-11-10',
    ]);
}

function parseCsv(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    return array_map(
        fn (string $line) => str_getcsv($line, ',', '"', ''),
        preg_split('/\r\n|\n/', trim($content)),
    );
}

it('keeps guests out of the export', function () {
    $this->get('/export/transactions')->assertRedirect('/login');
});

it('exports this months transactions as CSV by default', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $wallet = $this->user->accounts()->create(['name' => 'Wallet', 'type' => 'cash', 'opening_balance' => 0]);

    exportTx($this, [
        'occurred_on' => '2026-11-10',
        'amount' => 450.50,
        'merchant' => 'Bhat-Bhateni',
        'category_id' => $groceries->id,
        'note' => 'weekly shop',
    ]);
    exportTx($this, ['occurred_on' => '2026-11-12', 'type' => 'transfer', 'amount' => 300, 'transfer_account_id' => $wallet->id]);
    exportTx($this, ['occurred_on' => '2026-11-02', 'type' => 'income', 'amount' => 5000, 'merchant' => 'Salary']);
    exportTx($this, ['occurred_on' => '2025-03-01', 'merchant' => 'Old']);
    exportTx($this, ['occurred_on' => '2026-11-03', 'merchant' => 'Deleted'])->delete();

    $other = User::factory()->create();
    $otherAccount = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);
    Transaction::create([
        'user_id' => $other->id,
        'account_id' => $otherAccount->id,
        'type' => 'expense',
        'amount' => 1,
        'occurred_on' => '2026-11-05',
        'merchant' => 'Theirs',
    ]);

    $response = $this->actingAs($this->user)->get('/export/transactions');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('transactions-2026-11-24.csv');

    $content = $response->streamedContent();
    $rows = parseCsv($content);

    expect($content)->toStartWith("\xEF\xBB\xBFDate,Type,Amount,Merchant,Category,Account,To account,Note")
        ->and($rows)->toHaveCount(4)
        ->and($rows[1])->toBe(['2026-11-02', 'income', '5000.00', 'Salary', '', 'Main', '', ''])
        ->and($rows[2])->toBe(['2026-11-10', 'expense', '450.50', 'Bhat-Bhateni', 'Groceries', 'Main', '', 'weekly shop'])
        ->and($rows[3])->toBe(['2026-11-12', 'transfer', '300.00', '', '', 'Main', 'Wallet', '']);
});

it('respects the same filters as the transactions page', function () {
    exportTx($this, ['occurred_on' => '2025-03-01', 'merchant' => 'Old']);
    exportTx($this, ['occurred_on' => '2026-11-10', 'type' => 'income', 'merchant' => 'New']);

    $this->actingAs($this->user);

    $all = parseCsv($this->get('/export/transactions?range=all')->streamedContent());
    $income = parseCsv($this->get('/export/transactions?range=all&type=income')->streamedContent());

    expect($all)->toHaveCount(3)
        ->and($income)->toHaveCount(2)
        ->and($income[1][3])->toBe('New');
});

it('neutralises spreadsheet formulas', function () {
    exportTx($this, ['merchant' => '=HYPERLINK("http://evil")', 'note' => '+1 call me']);

    $rows = parseCsv($this->actingAs($this->user)->get('/export/transactions')->streamedContent());

    expect($rows[1][3])->toBe("'=HYPERLINK(\"http://evil\")")
        ->and($rows[1][7])->toBe("'+1 call me");
});
