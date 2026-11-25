<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Support\Csv;
use App\Support\TransactionFilter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $filter = TransactionFilter::fromRequest($request);

        $query = $filter->apply(Transaction::query()->where('user_id', $request->user()->id))
            ->with(['account', 'transferAccount', 'category'])
            ->orderBy('occurred_on')
            ->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // Byte-order mark so Excel opens the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");

            fwrite($out, "Date,Type,Amount,Merchant,Category,Account,To account,Note\n");

            $query->chunk(500, function ($transactions) use ($out) {
                foreach ($transactions as $t) {
                    fputcsv($out, [
                        $t->occurred_on->format('Y-m-d'),
                        $t->type->value,
                        number_format((float) $t->amount, 2, '.', ''),
                        Csv::safe($t->merchant),
                        Csv::safe($t->category?->name),
                        Csv::safe($t->account->name),
                        Csv::safe($t->transferAccount?->name),
                        Csv::safe($t->note),
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, 'transactions-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
