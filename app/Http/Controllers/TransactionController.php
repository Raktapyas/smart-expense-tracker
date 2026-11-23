<?php

namespace App\Http\Controllers;

use App\Actions\SaveTransaction;
use App\Http\Requests\TransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = $request->user()->transactions()
            ->with(['account', 'transferAccount', 'category'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(15);

        return view('transactions.index', compact('transactions'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->accounts()->where('is_archived', false)->exists()) {
            return redirect()->route('accounts.create')
                ->with('status', 'Add an account before recording transactions.');
        }

        return view('transactions.create', $this->formData($request));
    }

    public function store(TransactionRequest $request, SaveTransaction $save): RedirectResponse
    {
        $save($request->user(), $request->validated());

        return redirect()->route('transactions.index')->with('status', 'Transaction added.');
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        Gate::authorize('update', $transaction);

        return view('transactions.edit', ['transaction' => $transaction] + $this->formData($request, $transaction));
    }

    public function update(TransactionRequest $request, Transaction $transaction, SaveTransaction $save): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $save($request->user(), $request->validated(), $transaction);

        return redirect()->route('transactions.index')->with('status', 'Transaction updated.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $transaction->delete();

        return redirect()->route('transactions.index')->with('status', 'Transaction deleted.');
    }

    private function formData(Request $request, ?Transaction $transaction = null): array
    {
        $accounts = $request->user()->accounts()
            ->where(function ($query) use ($transaction) {
                $query->where('is_archived', false);

                if ($transaction) {
                    $query->orWhereIn('id', array_filter([$transaction->account_id, $transaction->transfer_account_id]));
                }
            })
            ->orderBy('name')
            ->get();

        $categories = Category::availableTo($request->user()->id)->orderBy('name')->get();

        return compact('accounts', 'categories');
    }
}
