<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = $request->user()->accounts()
            ->withBalance()
            ->orderBy('is_archived')
            ->orderBy('name')
            ->get();

        $total = round($accounts->where('is_archived', false)->sum(fn (Account $a) => $a->balance), 2);

        return view('accounts.index', compact('accounts', 'total'));
    }

    public function create(): View
    {
        return view('accounts.create');
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $request->user()->accounts()->create($request->validated());

        return redirect()->route('accounts.index')->with('status', 'Account created.');
    }

    public function edit(Account $account): View
    {
        Gate::authorize('update', $account);

        return view('accounts.edit', compact('account'));
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $account->update($request->validated());

        return redirect()->route('accounts.index')->with('status', 'Account updated.');
    }

    public function archive(Account $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $account->update(['is_archived' => ! $account->is_archived]);

        return redirect()->route('accounts.index')
            ->with('status', $account->is_archived ? 'Account archived.' : 'Account restored.');
    }
}
