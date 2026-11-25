<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = $request->user()->accounts()
            ->withBalance()
            ->orderBy('is_archived')
            ->orderBy('name')
            ->get();

        $total = round($accounts->where('is_archived', false)->sum(fn (Account $a) => $a->balance), 2);

        return AccountResource::collection($accounts)
            ->additional(['meta' => ['total_balance' => number_format($total, 2, '.', '')]]);
    }

    public function store(AccountRequest $request): JsonResponse
    {
        $account = $request->user()->accounts()->create($request->validated());

        return (new AccountResource($this->withBalance($account)))->response()->setStatusCode(201);
    }

    public function update(AccountRequest $request, Account $account): AccountResource
    {
        Gate::authorize('update', $account);

        $account->update($request->validated());

        return new AccountResource($this->withBalance($account));
    }

    /** Archives an active account, or restores an archived one. */
    public function archive(Account $account): AccountResource
    {
        Gate::authorize('update', $account);

        $account->update(['is_archived' => ! $account->is_archived]);

        return new AccountResource($this->withBalance($account));
    }

    private function withBalance(Account $account): Account
    {
        return Account::withBalance()->findOrFail($account->id);
    }
}
