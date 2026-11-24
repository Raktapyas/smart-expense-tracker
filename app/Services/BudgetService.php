<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class BudgetService
{
    /**
     * Progress of every budget for the current calendar month, most-used first.
     * Only expenses count; income, transfers and other months are ignored.
     */
    public function progress(User $user): Collection
    {
        $budgets = $user->budgets()->with('category')->get();

        if ($budgets->isEmpty()) {
            return collect();
        }

        $spentByCategory = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Expense->value)
            ->whereDate('occurred_on', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('occurred_on', '<=', now()->endOfMonth()->toDateString())
            ->toBase()
            ->selectRaw('category_id, SUM(amount) AS total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $totalSpent = (float) $spentByCategory->sum();

        return $budgets->map(function (Budget $budget) use ($spentByCategory, $totalSpent) {
            $limit = (float) $budget->amount;
            $spent = round(
                $budget->category_id === null
                    ? $totalSpent
                    : (float) ($spentByCategory[$budget->category_id] ?? 0),
                2,
            );
            $percent = $limit > 0 ? round($spent / $limit * 100, 1) : 0.0;

            return [
                'budget' => $budget,
                'name' => $budget->category?->name ?? 'Overall monthly budget',
                'color' => $budget->category?->color ?? '#10B981',
                'limit' => $limit,
                'spent' => $spent,
                'remaining' => round(max(0, $limit - $spent), 2),
                'percent' => $percent,
                'status' => $spent > $limit ? 'over' : ($percent >= 80 ? 'warning' : 'ok'),
            ];
        })->sortByDesc('percent')->values();
    }
}
