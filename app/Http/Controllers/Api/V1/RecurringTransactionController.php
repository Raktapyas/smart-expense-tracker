<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveRecurringTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecurringRequest;
use App\Http\Resources\RecurringTransactionResource;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\RecurringTransactionRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecurringTransactionController extends Controller
{
    private const RELATIONS = ['account', 'transferAccount', 'category'];

    public function index(Request $request): AnonymousResourceCollection
    {
        return RecurringTransactionResource::collection(
            $request->user()->recurringTransactions()
                ->with(self::RELATIONS)
                ->orderByDesc('is_active')
                ->orderBy('next_run_on')
                ->get()
        );
    }

    /** Anything already due (a start date today or earlier) is created immediately. */
    public function store(
        RecurringRequest $request,
        SaveRecurringTransaction $save,
        RecurringTransactionRunner $runner,
    ): JsonResponse {
        $recurring = $save($request->user(), $request->validated());

        $created = $runner->runFor($request->user());

        return (new RecurringTransactionResource($recurring->fresh(self::RELATIONS)))
            ->additional(['meta' => ['transactions_created' => $created]])
            ->response()
            ->setStatusCode(201);
    }

    /** Type, frequency and start date cannot be changed; changes apply to future transactions only. */
    public function update(
        RecurringRequest $request,
        RecurringTransaction $recurring,
        SaveRecurringTransaction $save,
    ): RecurringTransactionResource {
        Gate::authorize('update', $recurring);

        $save($request->user(), $request->validated(), $recurring);

        return new RecurringTransactionResource($recurring->fresh(self::RELATIONS));
    }

    /** Pauses an active schedule, or resumes a paused one without back-filling missed dates. */
    public function toggle(RecurringTransaction $recurring): RecurringTransactionResource
    {
        Gate::authorize('update', $recurring);

        if ($recurring->is_active) {
            $recurring->update(['is_active' => false]);
        } else {
            $recurring->skipMissed(now());

            if ($recurring->ends_on && $recurring->next_run_on->gt($recurring->ends_on)) {
                $recurring->save();

                throw ValidationException::withMessages([
                    'ends_on' => ['This schedule has ended. Change its end date to resume it.'],
                ]);
            }

            $recurring->is_active = true;
            $recurring->save();
        }

        return new RecurringTransactionResource($recurring->fresh(self::RELATIONS));
    }

    public function destroy(RecurringTransaction $recurring): Response
    {
        Gate::authorize('delete', $recurring);

        Transaction::withTrashed()
            ->where('recurring_transaction_id', $recurring->id)
            ->update(['recurring_transaction_id' => null]);

        $recurring->delete();

        return response()->noContent();
    }
}
