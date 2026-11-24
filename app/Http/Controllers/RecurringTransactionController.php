<?php

namespace App\Http\Controllers;

use App\Actions\SaveRecurringTransaction;
use App\Http\Requests\RecurringRequest;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\RecurringTransactionRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RecurringTransactionController extends Controller
{
    public function index(Request $request): View
    {
        return view('recurring.index', [
            'items' => $request->user()->recurringTransactions()
                ->with(['account', 'transferAccount', 'category'])
                ->orderByDesc('is_active')
                ->orderBy('next_run_on')
                ->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->accounts()->where('is_archived', false)->exists()) {
            return redirect()->route('accounts.create')
                ->with('status', 'Add an account before setting up recurring transactions.');
        }

        return view('recurring.create', $this->formData($request));
    }

    public function store(
        RecurringRequest $request,
        SaveRecurringTransaction $save,
        RecurringTransactionRunner $runner,
    ): RedirectResponse {
        $save($request->user(), $request->validated());

        $created = $runner->runFor($request->user());

        $status = $created > 0
            ? "Recurring transaction saved. {$created} ".Str::plural('transaction', $created).' created.'
            : 'Recurring transaction saved.';

        return redirect()->route('recurring.index')->with('status', $status);
    }

    public function edit(Request $request, RecurringTransaction $recurring): View
    {
        Gate::authorize('update', $recurring);

        return view('recurring.edit', ['recurring' => $recurring] + $this->formData($request, $recurring));
    }

    public function update(
        RecurringRequest $request,
        RecurringTransaction $recurring,
        SaveRecurringTransaction $save,
    ): RedirectResponse {
        Gate::authorize('update', $recurring);

        $save($request->user(), $request->validated(), $recurring);

        return redirect()->route('recurring.index')->with('status', 'Recurring transaction updated.');
    }

    public function toggle(RecurringTransaction $recurring): RedirectResponse
    {
        Gate::authorize('update', $recurring);

        if ($recurring->is_active) {
            $recurring->update(['is_active' => false]);

            return redirect()->route('recurring.index')->with('status', 'Paused.');
        }

        // Resuming never back-fills the dates missed while paused.
        $recurring->skipMissed(now());

        if ($recurring->ends_on && $recurring->next_run_on->gt($recurring->ends_on)) {
            $recurring->save();

            return redirect()->route('recurring.index')
                ->with('status', 'This schedule has ended. Change its end date to resume it.');
        }

        $recurring->is_active = true;
        $recurring->save();

        return redirect()->route('recurring.index')
            ->with('status', 'Resumed. Next on '.$recurring->next_run_on->format('d M Y').'.');
    }

    public function destroy(RecurringTransaction $recurring): RedirectResponse
    {
        Gate::authorize('delete', $recurring);

        Transaction::withTrashed()
            ->where('recurring_transaction_id', $recurring->id)
            ->update(['recurring_transaction_id' => null]);

        $recurring->delete();

        return redirect()->route('recurring.index')
            ->with('status', 'Recurring transaction deleted. Transactions it already created were kept.');
    }

    private function formData(Request $request, ?RecurringTransaction $recurring = null): array
    {
        $accounts = $request->user()->accounts()
            ->where(function ($query) use ($recurring) {
                $query->where('is_archived', false);

                if ($recurring) {
                    $query->orWhereIn('id', array_filter([$recurring->account_id, $recurring->transfer_account_id]));
                }
            })
            ->orderBy('name')
            ->get();

        $categories = Category::availableTo($request->user()->id)->orderBy('name')->get();

        return compact('accounts', 'categories');
    }
}
