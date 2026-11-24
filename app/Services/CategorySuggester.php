<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CategorySuggestion;

class CategorySuggester
{
    /**
     * Suggests a category for a merchant: the user's longest matching rule first,
     * then the category they used most for that exact merchant before.
     */
    public function suggest(User $user, ?string $merchant, string $type): ?CategorySuggestion
    {
        $merchant = trim((string) $merchant);

        if ($merchant === '' || ! in_array($type, ['income', 'expense'], true)) {
            return null;
        }

        $rule = CategoryRule::query()
            ->where('user_id', $user->id)
            ->whereHas('category', fn ($query) => $query->where('type', $type))
            ->with('category')
            ->get()
            ->filter(fn (CategoryRule $rule) => mb_stripos($merchant, $rule->keyword) !== false)
            ->sortByDesc(fn (CategoryRule $rule) => mb_strlen($rule->keyword))
            ->first();

        if ($rule) {
            return new CategorySuggestion($rule->category, 'rule');
        }

        $row = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereNotNull('category_id')
            ->whereRaw('LOWER(merchant) = ?', [mb_strtolower($merchant)])
            ->toBase()
            ->selectRaw('category_id, COUNT(*) AS uses, MAX(occurred_on) AS last_used')
            ->groupBy('category_id')
            ->orderByDesc('uses')
            ->orderByDesc('last_used')
            ->first();

        $category = $row ? Category::find($row->category_id) : null;

        return $category ? new CategorySuggestion($category, 'history') : null;
    }

    /** Categorises the user's existing uncategorised transactions that match a new rule. */
    public function applyToExisting(User $user, CategoryRule $rule): int
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereNull('category_id')
            ->where('type', $rule->category->type->value)
            ->whereLike('merchant', '%'.$rule->keyword.'%')
            ->update(['category_id' => $rule->category_id]);
    }
}
