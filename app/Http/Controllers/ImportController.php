<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportOptionsRequest;
use App\Http\Requests\UploadImportRequest;
use App\Services\CsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $accounts = $request->user()->accounts()->where('is_archived', false)->orderBy('name')->get();

        if ($accounts->isEmpty()) {
            return redirect()->route('accounts.create')
                ->with('status', 'Add an account before importing transactions.');
        }

        return view('import.create', [
            'accountOptions' => $accounts->mapWithKeys(fn ($a) => [$a->id => $a->name])->all(),
            'dateFormatOptions' => collect(CsvImporter::DATE_FORMATS)->map(fn ($f) => $f['label'])->all(),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['date', 'type', 'amount', 'merchant', 'category', 'account', 'note'], ',', '"', '');
            fputcsv($out, ['2026-11-10', 'expense', '450.50', 'Bhat-Bhateni', 'Groceries', '', 'Weekly shopping'], ',', '"', '');
            fputcsv($out, ['2026-11-11', 'income', '50000', 'Salary', 'Salary', '', ''], ',', '"', '');

            fclose($out);
        }, 'import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function upload(UploadImportRequest $request): RedirectResponse
    {
        $token = (string) Str::uuid();

        $request->file('file')->storeAs('imports/'.$request->user()->id, $token.'.csv', 'local');

        $options = $request->options();

        return redirect()->route('import.show', [
            'token' => $token,
            'default_account_id' => $options['default_account_id'],
            'date_format' => $options['date_format'],
            'skip_duplicates' => $options['skip_duplicates'] ? 1 : 0,
        ]);
    }

    public function show(ImportOptionsRequest $request, string $token, CsvImporter $importer): View
    {
        $options = $request->options();

        return view('import.show', [
            'result' => $importer->analyse($request->user(), $this->pathFor($request, $token), $options),
            'options' => $options,
            'token' => $token,
        ]);
    }

    public function store(ImportOptionsRequest $request, string $token, CsvImporter $importer): RedirectResponse
    {
        $path = $this->pathFor($request, $token);

        $result = $importer->import($request->user(), $path, $request->options());

        Storage::disk('local')->delete($path);

        if ($result->fatal !== null) {
            return redirect()->route('import.create')->with('status', $result->fatal);
        }

        $created = $result->count('ok');
        $duplicates = $result->count('duplicate');
        $errors = $result->count('error');

        $parts = ["Imported {$created} ".Str::plural('transaction', $created).'.'];

        if ($duplicates > 0) {
            $parts[] = "Skipped {$duplicates} ".Str::plural('duplicate', $duplicates).'.';
        }

        if ($errors > 0) {
            $parts[] = $errors === 1
                ? '1 row had errors and was not imported.'
                : "{$errors} rows had errors and were not imported.";
        }

        return redirect()->route('transactions.index', ['range' => 'all'])->with('status', implode(' ', $parts));
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        Storage::disk('local')->delete($this->pathFor($request, $token));

        return redirect()->route('import.create')->with('status', 'Import cancelled.');
    }

    /** Uploads live in a folder per user, so one user can never open another user's file. */
    private function pathFor(Request $request, string $token): string
    {
        $path = 'imports/'.$request->user()->id.'/'.$token.'.csv';

        abort_unless(Storage::disk('local')->exists($path), 404);

        return $path;
    }
}
