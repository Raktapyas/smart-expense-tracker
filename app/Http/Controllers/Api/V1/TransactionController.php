<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Support\TransactionFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    private const RELATIONS = ['account', 'transferAccount', 'category'];

    /**
     * Same filters as the website: range, type, account_id, category_id, q.
     * Defaults to this month. Also returns income/expense totals for the filtered set.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filter = TransactionFilter::fromRequest($request);

        $query = $filter->apply(Transaction::query()->where('user_id', $request->user()->id));

        $totals = (clone $query)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense")
            ->first();

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $transactions = (clone $query)
            ->with(self::RELATIONS)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return TransactionResource::collection($transactions)->additional([
            'totals' => [
                'range' => $filter->range,
                'income' => number_format((float) $totals->income, 2, '.', ''),
                'expense' => number_format((float) $totals->expense, 2, '.', ''),
            ],
        ]);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        Gate::authorize('update', $transaction);

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function store(TransactionRequest $request, SaveTransaction $save): JsonResponse
    {
        $transaction = $save($request->user(), $request->validated());

        $autoCategorised = empty($request->validated()['category_id']) && $transaction->category_id !== null;

        return (new TransactionResource($transaction->load(self::RELATIONS)))
            ->additional(['meta' => ['auto_categorised' => $autoCategorised]])
            ->response()
            ->setStatusCode(201);
    }

    public function update(TransactionRequest $request, Transaction $transaction, SaveTransaction $save): TransactionResource
    {
        Gate::authorize('update', $transaction);

        $save($request->user(), $request->validated(), $transaction);

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function destroy(Transaction $transaction): Response
    {
        Gate::authorize('delete', $transaction);

        $transaction->delete();

        return response()->noContent();
    }
}
