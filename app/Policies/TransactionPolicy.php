<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function update(User $user, Transaction $transaction): bool
    {
        return (int) $transaction->user_id === (int) $user->id;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
