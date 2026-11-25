<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CsvImporter;
use App\Support\ImportResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-24 12:00:00');
    $this->user = User::factory()->create();
    $this->account = $this->user->accounts()->create([
        'name' => 'Main',
        'type' => 'bank',
        'opening_balance' => 0,
    ]);
});

function importOptions($test, array $overrides = []): array
{
    return $overrides + [
        'default_account_id' => $test->account->id,
        'date_format' => 'ymd',
        'skip_duplicates' => true,
    ];
}

function analyseCsv($test, string $csv, array $options = []): ImportResult
{
    $path = 'imports/'.$test->user->id.'/'.Str::uuid().'.csv';
    Storage::disk('local')->put($path, $csv);

    return app(CsvImporter::class)->analyse($test->user, $path, importOptions($test, $options));
}

function csvLines(array $lines): string
{
    return implode("\n", $lines);
}

function uploadCsv($test, string $csv, array $overrides = []): string
{
    $response = $test->actingAs($test->user)->post('/import', $overrides + [
        'file' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
        'default_account_id' => $test->account->id,
        'date_format' => 'ymd',
        'skip_duplicates' => '1',
    ])->assertRedirect();

    preg_match('#/import/([0-9a-f-]{36})#', $response->headers->get('Location'), $matches);

    return $matches[1];
}

function confirmFields($test): array
{
    return ['default_account_id' => $test->account->id, 'date_format' => 'ymd', 'skip_duplicates' => '1'];
}

it('reads our own export format', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);

    $result = analyseCsv($this, csvLines([
        'date,type,amount,merchant,category,account,note',
        '2026-11-10,expense,450.50,Bhat-Bhateni,Groceries,Main,weekly shop',
        '2026-11-11,income,50000,Salary,,Main,',
    ]));

    $first = $result->rows[0]['data'];
    $second = $result->rows[1]['data'];

    expect($result->fatal)->toBeNull()
        ->and($result->count('ok'))->toBe(2)
        ->and($first['type'])->toBe('expense')
        ->and($first['amount'])->toBe('450.50')
        ->and($first['occurred_on'])->toBe('2026-11-10')
        ->and($first['merchant'])->toBe('Bhat-Bhateni')
        ->and($first['category_id'])->toEqual($groceries->id)
        ->and($first['account_id'])->toEqual($this->account->id)
        ->and($first['note'])->toBe('weekly shop')
        ->and($second['type'])->toBe('income')
        ->and($second['amount'])->toBe('50000.00')
        ->and($second['category_id'])->toBeNull();
});

it('reads a bank statement with semicolons, debit and credit columns and currency text', function () {
    $csv = "\xEF\xBB\xBF".csvLines([
        'Transaction Date;Description;Debit;Credit',
        '5/11/2026;ATM Withdrawal;"Rs. 2,000.00";',
        '06/11/2026;Salary Nov;;"85,000.00"',
    ]);

    $result = analyseCsv($this, $csv, ['date_format' => 'dmy']);

    expect($result->count('ok'))->toBe(2)
        ->and($result->rows[0]['data']['occurred_on'])->toBe('2026-11-05')
        ->and($result->rows[0]['data']['type'])->toBe('expense')
        ->and($result->rows[0]['data']['amount'])->toBe('2000.00')
        ->and($result->rows[0]['data']['merchant'])->toBe('ATM Withdrawal')
        ->and($result->rows[1]['data']['occurred_on'])->toBe('2026-11-06')
        ->and($result->rows[1]['data']['type'])->toBe('income')
        ->and($result->rows[1]['data']['amount'])->toBe('85000.00');
});

it('treats signed amounts as expenses and income', function () {
    $result = analyseCsv($this, csvLines([
        'date,amount,description',
        '2026-11-01,-250,Taxi',
        '2026-11-02,"1,000",Refund',
        '2026-11-03,(75.50),Snacks',
    ]));

    expect($result->count('ok'))->toBe(3)
        ->and($result->rows[0]['data']['type'])->toBe('expense')
        ->and($result->rows[0]['data']['amount'])->toBe('250.00')
        ->and($result->rows[1]['data']['type'])->toBe('income')
        ->and($result->rows[1]['data']['amount'])->toBe('1000.00')
        ->and($result->rows[2]['data']['type'])->toBe('expense')
        ->and($result->rows[2]['data']['amount'])->toBe('75.50');
});

it('explains why a row cannot be imported', function () {
    $result = analyseCsv($this, csvLines([
        'date,type,amount,account,to account',
        ',expense,10,Main,',
        '2026-13-45,expense,10,Main,',
        '2026-11-01,expense,abc,Main,',
        '2026-11-01,bogus,10,Main,',
        '2026-11-01,expense,10,Nowhere,',
        '2026-11-01,expense,0,Main,',
        '2026-11-01,transfer,10,Main,',
        '2026-11-01,transfer,10,Main,Main',
    ]));

    $messages = collect($result->rows)->pluck('message', 'line');

    expect($result->count('error'))->toBe(8)
        ->and($messages[2])->toBe('Missing date.')
        ->and($messages[3])->toContain('Could not read the date "2026-13-45"')
        ->and($messages[4])->toBe('Could not read the amount "abc".')
        ->and($messages[5])->toContain('Unknown type "bogus"')
        ->and($messages[6])->toBe('Unknown account "Nowhere".')
        ->and($messages[7])->toBe('Amount must be greater than zero.')
        ->and($messages[8])->toBe('A transfer needs a "to account".')
        ->and($messages[9])->toBe('A transfer needs two different accounts.');
});

it('leaves unknown or wrong-type categories uncategorised with a warning', function () {
    Category::create(['name' => 'Salary', 'type' => 'income']);

    $result = analyseCsv($this, csvLines([
        'date,type,amount,category',
        '2026-11-01,expense,10,Nonexistent',
        '2026-11-02,expense,10,Salary',
    ]));

    expect($result->count('ok'))->toBe(2)
        ->and($result->rows[0]['data']['category_id'])->toBeNull()
        ->and($result->rows[0]['warning'])->toContain('Unknown category "Nonexistent"')
        ->and($result->rows[1]['data']['category_id'])->toBeNull()
        ->and($result->rows[1]['warning'])->toContain('Unknown category "Salary"');
});

it('reads transfers between accounts', function () {
    $wallet = $this->user->accounts()->create(['name' => 'Wallet', 'type' => 'cash', 'opening_balance' => 0]);

    $result = analyseCsv($this, csvLines([
        'date,type,amount,account,to account',
        '2026-11-05,transfer,300,Main,Wallet',
    ]));

    $data = $result->rows[0]['data'];

    expect($result->count('ok'))->toBe(1)
        ->and($data['type'])->toBe('transfer')
        ->and($data['account_id'])->toEqual($this->account->id)
        ->and($data['transfer_account_id'])->toEqual($wallet->id)
        ->and($data['category_id'])->toBeNull()
        ->and($data['amount'])->toBe('300.00');
});

it('flags rows that already exist but keeps identical rows within the file', function () {
    Transaction::create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'type' => 'expense',
        'amount' => 450.50,
        'occurred_on' => '2026-11-10',
        'merchant' => 'Bhat-Bhateni',
    ]);

    $csv = csvLines([
        'date,type,amount,merchant',
        '2026-11-10,expense,450.50,bhat-bhateni',
        '2026-11-11,expense,100,Tea',
        '2026-11-11,expense,100,Tea',
    ]);

    $skipping = analyseCsv($this, $csv);
    $keeping = analyseCsv($this, $csv, ['skip_duplicates' => false]);

    expect($skipping->count('duplicate'))->toBe(1)
        ->and($skipping->count('ok'))->toBe(2)
        ->and($keeping->count('duplicate'))->toBe(0)
        ->and($keeping->count('ok'))->toBe(3);
});

it('rejects files without the required columns or content', function () {
    expect(analyseCsv($this, "foo,bar\n1,2")->fatal)->toContain('Date column')
        ->and(analyseCsv($this, "date,merchant\n2026-11-01,Tea")->fatal)->toContain('Amount column')
        ->and(analyseCsv($this, '')->fatal)->toBe('The file is empty.')
        ->and(analyseCsv($this, 'date,amount')->fatal)->toBeNull()
        ->and(analyseCsv($this, 'date,amount')->count('ok'))->toBe(0);
});

it('limits the number of rows', function () {
    $lines = ['date,type,amount'];

    foreach (range(1, 5001) as $i) {
        $lines[] = '2026-11-10,expense,1';
    }

    expect(analyseCsv($this, csvLines($lines))->fatal)->toContain('Too many rows');
});

it('reads Windows-1252 text', function () {
    $result = analyseCsv($this, "date,amount,description\n2026-11-10,-100,Caf\xE9");

    expect($result->rows[0]['data']['merchant'])->toBe('Café');
});

it('keeps guests out and sends users without accounts to create one', function () {
    $this->get('/import')->assertRedirect('/login');
    $this->get('/import/template')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())->get('/import')->assertRedirect('/accounts/create');
});

it('previews an upload and imports the ready rows with auto-categorisation', function () {
    $groceries = Category::create(['name' => 'Groceries', 'type' => 'expense']);
    $this->user->categoryRules()->create(['keyword' => 'bhat', 'category_id' => $groceries->id]);

    $token = uploadCsv($this, csvLines([
        'date,type,amount,merchant',
        '2026-11-10,expense,450.50,Bhat-Bhateni',
        '2026-11-11,income,50000,Salary',
        'oops,expense,5,Broken',
    ]));

    $this->get("/import/{$token}?default_account_id={$this->account->id}&date_format=ymd&skip_duplicates=1")
        ->assertOk()
        ->assertSee('Ready to import')
        ->assertSee('Bhat-Bhateni')
        ->assertSee('Line 4');

    expect(Transaction::count())->toBe(0);

    $this->post("/import/{$token}", confirmFields($this))
        ->assertRedirect('/transactions?range=all')
        ->assertSessionHas('status', 'Imported 2 transactions. 1 row had errors and was not imported.');

    expect(Transaction::count())->toBe(2)
        ->and(Transaction::where('merchant', 'Bhat-Bhateni')->first()->category_id)->toEqual($groceries->id);

    Storage::disk('local')->assertMissing("imports/{$this->user->id}/{$token}.csv");

    $this->post("/import/{$token}", confirmFields($this))->assertNotFound();
});

it('skips duplicates when the same file is imported again', function () {
    $csv = csvLines([
        'date,type,amount,merchant',
        '2026-11-10,expense,450.50,Bhat-Bhateni',
        '2026-11-11,income,50000,Salary',
    ]);

    $first = uploadCsv($this, $csv);
    $this->post("/import/{$first}", confirmFields($this));

    $second = uploadCsv($this, $csv);
    $this->post("/import/{$second}", confirmFields($this))
        ->assertSessionHas('status', 'Imported 0 transactions. Skipped 2 duplicates.');

    expect(Transaction::count())->toBe(2);
});

it('validates the upload', function () {
    $other = User::factory()->create();
    $theirs = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);

    $this->actingAs($this->user)->post('/import', [
        'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        'default_account_id' => $this->account->id,
        'date_format' => 'ymd',
    ])->assertSessionHasErrors('file');

    $this->post('/import', [
        'file' => UploadedFile::fake()->createWithContent('statement.csv', "date,amount\n2026-11-01,5"),
        'default_account_id' => $theirs->id,
        'date_format' => 'ymd',
    ])->assertSessionHasErrors('default_account_id');
});

it('hides uploads from other users and deletes them on cancel', function () {
    $token = uploadCsv($this, "date,amount\n2026-11-01,5");

    $stranger = User::factory()->create();
    $strangerAccount = $stranger->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);

    $this->actingAs($stranger)
        ->get("/import/{$token}?default_account_id={$strangerAccount->id}&date_format=ymd")
        ->assertNotFound();

    $this->actingAs($this->user)->delete("/import/{$token}")
        ->assertRedirect('/import')
        ->assertSessionHas('status', 'Import cancelled.');

    Storage::disk('local')->assertMissing("imports/{$this->user->id}/{$token}.csv");
});

it('offers a CSV template', function () {
    $response = $this->actingAs($this->user)->get('/import/template');

    $response->assertOk();
    expect($response->streamedContent())->toContain('date,type,amount,merchant,category,account,note');
});

it('prunes old uploaded files', function () {
    $this->travelBack();

    Storage::disk('local')->put('imports/1/old.csv', 'x');
    Storage::disk('local')->put('imports/1/new.csv', 'x');
    touch(Storage::disk('local')->path('imports/1/old.csv'), time() - 90000);

    $this->artisan('imports:prune')
        ->expectsOutput('Deleted 1 old import file(s).')
        ->assertSuccessful();

    Storage::disk('local')->assertMissing('imports/1/old.csv');
    Storage::disk('local')->assertExists('imports/1/new.csv');
});
