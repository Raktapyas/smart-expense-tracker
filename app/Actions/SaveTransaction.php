<?php

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategorySuggester;

class SaveTransaction
{
    public function __construct(private CategorySuggester $suggester) {}

    /**
     * Creates or updates a transaction from validated data.
     * Transfers never have a category; income/expense never have a destination account.
     * New income/expense transactions without a category get one from the user's
     * rules or history. Updates are never re-categorised.
     */
    public function __invoke(User $user, array $data, ?Transaction $transaction = null): Transaction
    {
        $isTransfer = $data['type'] === TransactionType::Transfer->value;

        $attributes = [
            'type' => $data['type'],
            'account_id' => $data['account_id'],
            'amount' => $data['amount'],
            'occurred_on' => $data['occurred_on'],
            'merchant' => $data['merchant'] ?? null,
            'note' => $data['note'] ?? null,
            'category_id' => $isTransfer ? null : ($data['category_id'] ?? null),
            'transfer_account_id' => $isTransfer ? ($data['transfer_account_id'] ?? null) : null,
        ];

        if (! $transaction && ! $isTransfer && empty($attributes['category_id'])) {
            $suggestion = $this->suggester->suggest($user, $attributes['merchant'], $attributes['type']);
            $attributes['category_id'] = $suggestion?->category->id;
        }

        if ($transaction) {
            $transaction->update($attributes);

            return $transaction;
        }

        return $user->transactions()->create($attributes);
    }
}
