<?php

namespace App\Policies;

use App\Models\RecurringTransaction;
use App\Models\User;

class RecurringTransactionPolicy
{
    public function update(User $user, RecurringTransaction $recurring): bool
    {
        return (int) $recurring->user_id === (int) $user->id;
    }

    public function delete(User $user, RecurringTransaction $recurring): bool
    {
        return $this->update($user, $recurring);
    }
}
