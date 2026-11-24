<?php

namespace App\Policies;

use App\Models\CategoryRule;
use App\Models\User;

class CategoryRulePolicy
{
    public function delete(User $user, CategoryRule $rule): bool
    {
        return (int) $rule->user_id === (int) $user->id;
    }
}
