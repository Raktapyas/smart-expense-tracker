<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function update(User $user, Account $account): bool
    {
        return (int) $account->user_id === (int) $user->id;
    }
}
