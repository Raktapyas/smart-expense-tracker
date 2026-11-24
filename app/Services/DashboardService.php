<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private BudgetService $budgets, private InsightService $insights) {}

    public function build(User $user): array
    {
        $accounts = $user->accounts()
            ->withBalance()
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        // A default TransactionFilter means "this month".
        $monthQuery = (new TransactionFilter)->apply(Transaction::query()->where('user_id', $user->id));

        $totals = (clone $monthQuery)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense")
            ->first();

        $income = round((float) $totals->income, 2);
        $expense = round((float) $totals->expense, 2);

        $budgetProgress = $this->budgets->progress($user);

        return [
            'monthLabel' => now()->format('F Y'),
            'accounts' => $accounts,
            'totalBalance' => round($accounts->sum(fn ($account) => $account->balance), 2),
            'income' => $income,
            'expense' => $expense,
            'saved' => round($income - $expense, 2),
            'categoryBreakdown' => $this->categoryBreakdown(clone $monthQuery),
            'trend' => $this->trend($user),
            'budgets' => $budgetProgress->take(4)->values(),
            'overBudgetCount' => $budgetProgress->where('status', 'over')->count(),
            'insights' => $this->insights->report($user)['insights']->take(4)->values(),
            'recent' => $user->transactions()
                ->with(['account', 'transferAccount', 'category'])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
        ];
    }

    /** Top 5 expense categories this month; the rest are folded into "Everything else". */
    private function categoryBreakdown(Builder $monthQuery): Collection
    {
        $rows = $monthQuery
            ->where('type', TransactionType::Expense->value)
            ->toBase()
            ->selectRaw('category_id, SUM(amount) AS total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $grand = (float) $rows->sum('total');
        $categories = Category::whereIn('id', $rows->pluck('category_id')->filter())->get()->keyBy('id');

        $items = $rows->map(function ($row) use ($categories, $grand) {
            $category = $categories->get($row->category_id);

            return [
                'name' => $category?->name ?? 'Uncategorised',
                'color' => $category?->color ?? '#64748B',
                'total' => round((float) $row->total, 2),
                'percent' => round(((float) $row->total / $grand) * 100, 1),
            ];
        });

        if ($items->count() > 6) {
            $top = $items->take(5);
            $rest = $items->slice(5);

            $top->push([
                'name' => 'Everything else',
                'color' => '#94A3B8',
                'total' => round($rest->sum('total'), 2),
                'percent' => round($rest->sum('percent'), 1),
            ]);

            return $top->values();
        }

        return $items->values();
    }

    /** Income and spending for each of the last 6 months, oldest first. */
    private function trend(User $user): Collection
    {
        $start = now()->startOfMonth()->subMonthsNoOverflow(5);

        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereDate('occurred_on', '>=', $start->toDateString())
            ->get(['type', 'amount', 'occurred_on'])
            ->groupBy(fn ($transaction) => $transaction->occurred_on->format('Y-m'));

        return collect(range(5, 0))->map(function (int $monthsAgo) use ($rows) {
            $month = now()->startOfMonth()->subMonthsNoOverflow($monthsAgo);
            $items = $rows->get($month->format('Y-m'), collect());

            return [
                'label' => $month->format('M'),
                'income' => round((float) $items->where('type', TransactionType::Income)->sum('amount'), 2),
                'expense' => round((float) $items->where('type', TransactionType::Expense)->sum('amount'), 2),
            ];
        });
    }
}
