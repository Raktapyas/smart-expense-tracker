<?php

namespace App\Services;

use App\Actions\SaveTransaction;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ImportResult;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CsvImporter
{
    public const MAX_ROWS = 5000;

    public const DATE_FORMATS = [
        'ymd' => ['label' => 'YYYY-MM-DD (2026-11-24)', 'php' => 'Y-m-d'],
        'dmy' => ['label' => 'DD/MM/YYYY (24/11/2026)', 'php' => 'j/n/Y'],
        'mdy' => ['label' => 'MM/DD/YYYY (11/24/2026)', 'php' => 'n/j/Y'],
        'dmy-' => ['label' => 'DD-MM-YYYY (24-11-2026)', 'php' => 'j-n-Y'],
    ];

    private const ALIASES = [
        'date' => ['date', 'transaction date', 'txn date', 'value date', 'posting date'],
        'amount' => ['amount'],
        'debit' => ['debit', 'withdrawal', 'withdrawals', 'dr'],
        'credit' => ['credit', 'deposit', 'deposits', 'cr'],
        'type' => ['type'],
        'merchant' => ['merchant', 'payee', 'description', 'narration', 'details', 'particulars'],
        'category' => ['category'],
        'note' => ['note', 'notes', 'memo', 'remarks', 'reference'],
        'account' => ['account'],
        'to_account' => ['to account', 'transfer account', 'transfer to'],
    ];

    private const TYPE_ALIASES = [
        'income' => 'income',
        'credit' => 'income',
        'deposit' => 'income',
        'expense' => 'expense',
        'debit' => 'expense',
        'withdrawal' => 'expense',
        'transfer' => 'transfer',
    ];

    public function __construct(private SaveTransaction $save) {}

    /**
     * Reads a stored CSV file and reports, row by row, what would happen if it were
     * imported. Nothing is saved.
     *
     * @param  array{default_account_id: int, date_format: string, skip_duplicates: bool}  $options
     */
    public function analyse(User $user, string $path, array $options): ImportResult
    {
        $stream = Storage::disk('local')->readStream($path);

        if (! is_resource($stream)) {
            return $this->fatal('The uploaded file could not be read. Please upload it again.');
        }

        $firstLine = fgets($stream);

        if ($firstLine === false || trim($firstLine) === '') {
            fclose($stream);

            return $this->fatal('The file is empty.');
        }

        $delimiter = $this->detectDelimiter($firstLine);
        rewind($stream);

        $headerCells = fgetcsv($stream, 0, $delimiter, '"', '');
        $headers = array_map(
            fn ($cell) => $this->normaliseHeader((string) $cell),
            $this->utf8($headerCells ?: []),
        );
        $map = $this->mapColumns($headers);

        if (! isset($map['date'])) {
            fclose($stream);

            return $this->fatal('We could not find a Date column. The first row must be a header such as: date, type, amount, merchant, category, account, note.');
        }

        if (! isset($map['amount']) && ! isset($map['debit']) && ! isset($map['credit'])) {
            fclose($stream);

            return $this->fatal('We could not find an Amount column (or Debit and Credit columns).');
        }

        $accounts = $user->accounts()->get()->keyBy(fn ($account) => mb_strtolower($account->name));
        $categories = Category::availableTo($user->id)->get()
            ->sortBy(fn ($category) => $category->user_id === null ? 0 : 1)
            ->keyBy(fn ($category) => $category->type->value.'|'.mb_strtolower($category->name));

        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $line++;

            if (count(array_filter($cells, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($stream);

                return $this->fatal('Too many rows. Please import at most '.self::MAX_ROWS.' rows at a time.');
            }

            $rows[] = ['line' => $line] + $this->parseRow($this->utf8($cells), $map, $options, $accounts, $categories);
        }

        fclose($stream);

        if (($options['skip_duplicates'] ?? false) && $rows !== []) {
            $rows = $this->markDuplicates($user, $rows);
        }

        return new ImportResult($rows);
    }

    /** Imports every row that analyse() reports as ready. All or nothing for those rows. */
    public function import(User $user, string $path, array $options): ImportResult
    {
        $result = $this->analyse($user, $path, $options);

        if ($result->fatal !== null) {
            return $result;
        }

        DB::transaction(function () use ($user, $result) {
            foreach ($result->rows as $row) {
                if ($row['status'] === 'ok') {
                    ($this->save)($user, $row['data']);
                }
            }
        });

        return $result;
    }

    private function parseRow(array $cells, array $map, array $options, Collection $accounts, Collection $categories): array
    {
        $get = fn (string $key): string => isset($map[$key]) ? trim((string) ($cells[$map[$key]] ?? '')) : '';
        $fail = fn (string $message): array => ['status' => 'error', 'message' => $message, 'warning' => null, 'data' => null];

        $dateRaw = $get('date');

        if ($dateRaw === '') {
            return $fail('Missing date.');
        }

        $date = $this->parseDate($dateRaw, $options['date_format'] ?? 'ymd');

        if ($date === null) {
            return $fail("Could not read the date \"{$dateRaw}\" with the chosen date format.");
        }

        $type = null;
        $typeRaw = mb_strtolower($get('type'));

        if ($typeRaw !== '') {
            $type = self::TYPE_ALIASES[$typeRaw] ?? null;

            if ($type === null) {
                return $fail("Unknown type \"{$typeRaw}\" (use income, expense or transfer).");
            }
        }

        if ($get('amount') !== '') {
            $parsed = $this->parseAmount($get('amount'));

            if ($parsed === null) {
                return $fail('Could not read the amount "'.$get('amount').'".');
            }

            $amount = $parsed['value'];
            $type ??= $parsed['negative'] ? 'expense' : 'income';
        } else {
            $debit = $this->parseAmount($get('debit'));
            $credit = $this->parseAmount($get('credit'));

            if (($get('debit') !== '' && $debit === null) || ($get('credit') !== '' && $credit === null)) {
                return $fail('Could not read the debit or credit amount.');
            }

            $debitValue = $debit['value'] ?? 0.0;
            $creditValue = $credit['value'] ?? 0.0;

            if ($debitValue > 0 && $creditValue > 0) {
                return $fail('Row has both a debit and a credit.');
            }

            if ($debitValue > 0) {
                $amount = $debitValue;
                $type ??= 'expense';
            } elseif ($creditValue > 0) {
                $amount = $creditValue;
                $type ??= 'income';
            } else {
                return $fail('Missing amount.');
            }
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            return $fail('Amount must be greater than zero.');
        }

        if ($amount > 999999999999.99) {
            return $fail('Amount is too large.');
        }

        $accountId = (int) ($options['default_account_id'] ?? 0);
        $accountName = $get('account');

        if ($accountName !== '') {
            $account = $accounts->get(mb_strtolower($accountName));

            if (! $account) {
                return $fail("Unknown account \"{$accountName}\".");
            }

            $accountId = (int) $account->id;
        }

        $toAccountId = null;

        if ($type === 'transfer') {
            $toName = $get('to_account');

            if ($toName === '') {
                return $fail('A transfer needs a "to account".');
            }

            $to = $accounts->get(mb_strtolower($toName));

            if (! $to) {
                return $fail("Unknown account \"{$toName}\".");
            }

            if ((int) $to->id === $accountId) {
                return $fail('A transfer needs two different accounts.');
            }

            $toAccountId = (int) $to->id;
        }

        $categoryId = null;
        $warning = null;
        $categoryName = $get('category');

        if ($type !== 'transfer' && $categoryName !== '') {
            $category = $categories->get($type.'|'.mb_strtolower($categoryName));

            if ($category) {
                $categoryId = $category->id;
            } else {
                $warning = "Unknown category \"{$categoryName}\": left uncategorised.";
            }
        }

        $merchant = mb_substr($get('merchant'), 0, 120);
        $note = mb_substr($get('note'), 0, 1000);

        return [
            'status' => 'ok',
            'message' => null,
            'warning' => $warning,
            'data' => [
                'type' => $type,
                'account_id' => $accountId,
                'transfer_account_id' => $toAccountId,
                'category_id' => $categoryId,
                'amount' => number_format($amount, 2, '.', ''),
                'occurred_on' => $date,
                'merchant' => $merchant !== '' ? $merchant : null,
                'note' => $note !== '' ? $note : null,
            ],
        ];
    }

    /** Marks ready rows that match a transaction already in the database. */
    private function markDuplicates(User $user, array $rows): array
    {
        $dates = [];

        foreach ($rows as $row) {
            if ($row['status'] === 'ok') {
                $dates[] = $row['data']['occurred_on'];
            }
        }

        if ($dates === []) {
            return $rows;
        }

        $existing = Transaction::query()
            ->where('user_id', $user->id)
            ->whereDate('occurred_on', '>=', min($dates))
            ->whereDate('occurred_on', '<=', max($dates))
            ->get(['account_id', 'transfer_account_id', 'type', 'amount', 'occurred_on', 'merchant'])
            ->mapWithKeys(fn ($t) => [$this->key(
                $t->account_id,
                $t->transfer_account_id,
                $t->type->value,
                $t->occurred_on->toDateString(),
                $t->amount,
                $t->merchant,
            ) => true])
            ->all();

        foreach ($rows as $index => $row) {
            if ($row['status'] !== 'ok') {
                continue;
            }

            $d = $row['data'];

            $key = $this->key($d['account_id'], $d['transfer_account_id'], $d['type'], $d['occurred_on'], $d['amount'], $d['merchant']);

            if (isset($existing[$key])) {
                $rows[$index]['status'] = 'duplicate';
                $rows[$index]['message'] = 'Already exists.';
            }
        }

        return $rows;
    }

    private function key(mixed $accountId, mixed $toAccountId, string $type, string $date, mixed $amount, ?string $merchant): string
    {
        return implode('|', [
            (int) $accountId,
            (int) $toAccountId,
            $type,
            $date,
            number_format((float) $amount, 2, '.', ''),
            mb_strtolower((string) $merchant),
        ]);
    }

    private function parseDate(string $raw, string $formatKey): ?string
    {
        $format = self::DATE_FORMATS[$formatKey]['php'] ?? 'Y-m-d';

        // Ignore any time part, e.g. "2026-11-24 14:30".
        $raw = preg_split('/[\sT]/', $raw)[0];

        $date = DateTimeImmutable::createFromFormat('!'.$format, $raw);
        $errors = DateTimeImmutable::getLastErrors();

        if (! $date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        $year = (int) $date->format('Y');

        if ($year < 1990 || $year > ((int) now()->format('Y') + 1)) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /** @return array{value: float, negative: bool}|null */
    private function parseAmount(string $raw): ?array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $negative = str_contains($raw, '-') || (str_starts_with($raw, '(') && str_ends_with($raw, ')'));

        // Drop currency words such as "Rs." or "NPR", then everything except digits and the decimal point.
        $withoutLetters = preg_replace('/\p{L}+\.?/u', '', $raw) ?? '';
        $clean = preg_replace('/[^0-9.]/', '', $withoutLetters) ?? '';

        if ($clean === '' || substr_count($clean, '.') > 1 || ! is_numeric($clean)) {
            return null;
        }

        return ['value' => (float) $clean, 'negative' => $negative];
    }

    private function detectDelimiter(string $line): string
    {
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($line, $candidate);

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function normaliseHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = mb_strtolower(trim($header));

        return trim(preg_replace('/[_\-\s]+/u', ' ', $header) ?? $header);
    }

    private function mapColumns(array $headers): array
    {
        $map = [];

        foreach (self::ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                $index = array_search($alias, $headers, true);

                if ($index !== false) {
                    $map[$key] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /** Converts cells that are not valid UTF-8 (old Excel exports) from Windows-1252. */
    private function utf8(array $cells): array
    {
        return array_map(
            fn ($cell) => is_string($cell) && ! mb_check_encoding($cell, 'UTF-8')
                ? mb_convert_encoding($cell, 'UTF-8', 'Windows-1252')
                : $cell,
            $cells,
        );
    }

    private function fatal(string $message): ImportResult
    {
        return new ImportResult([], $message);
    }
}
