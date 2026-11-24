<?php

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use App\Models\User;

class SaveRecurringTransaction
{
    /**
     * Creates a schedule, or updates the editable details of an existing one.
     * Type, frequency and start date are fixed once a schedule exists.
     */
    public function __invoke(User $user, array $data, ?RecurringTransaction $recurring = null): RecurringTransaction
    {
        $type = $recurring?->type->value ?? $data['type'];
        $isTransfer = $type === TransactionType::Transfer->value;

        $attributes = [
            'account_id' => $data['account_id'],
            'amount' => $data['amount'],
            'merchant' => $data['merchant'] ?? null,
            'note' => $data['note'] ?? null,
            'category_id' => $isTransfer ? null : ($data['category_id'] ?? null),
            'transfer_account_id' => $isTransfer ? ($data['transfer_account_id'] ?? null) : null,
            'ends_on' => $data['ends_on'] ?? null,
        ];

        if ($recurring) {
            $recurring->update($attributes);

            return $recurring;
        }

        return $user->recurringTransactions()->create($attributes + [
            'type' => $type,
            'frequency' => $data['frequency'],
            'starts_on' => $data['starts_on'],
            'next_run_on' => $data['starts_on'],
        ]);
    }
}
