<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function update(User $user, Budget $budget): bool
    {
        return (int) $budget->user_id === (int) $user->id;
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->update($user, $budget);
    }
}
