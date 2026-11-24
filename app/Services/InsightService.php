<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class InsightService
{
    private const MIN_CHANGE = 500.0;

    private const MIN_PERCENT = 25;

    private const TONE_ORDER = ['bad' => 0, 'warn' => 1, 'info' => 2, 'good' => 3];

    /**
     * Plain-language insights about this month so far, compared with the same point
     * of last month, plus a per-category comparison. Transfers are never counted.
     *
     * @return array{insights: Collection, comparison: Collection, periodLabel: string}
     */
    public function report(User $user, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $lastStart = $monthStart->copy()->subMonthNoOverflow();
        $cutoff = $lastStart->copy()->day(min($today->day, $lastStart->daysInMonth));

        $current = $this->spendByCategory($user, $monthStart, $today);
        $previous = $this->spendByCategory($user, $lastStart, $cutoff);
        $spent = round((float) $current->sum(), 2);
        $prevSpent = round((float) $previous->sum(), 2);

        $ids = $current->keys()->merge($previous->keys())->unique()->values();
        $names = $this->categoryNames($ids);

        $insights = collect(array_merge(
            $this->pace($spent, $prevSpent),
            $this->projection($user, $spent, $today),
            $this->movers($ids, $current, $previous, $names),
            $this->biggestCategory($current, $spent, $names),
            $this->savings($user, $spent, $monthStart, $today),
            $this->upcoming($user, $today),
            $this->largestExpense($user, $monthStart, $today),
            $this->uncategorised($user, $monthStart, $today),
        ))->sortBy(fn (array $insight) => self::TONE_ORDER[$insight['tone']])->values();

        $comparison = $ids->map(function ($id) use ($current, $previous, $names) {
            $now = round((float) ($current[$id] ?? 0), 2);
            $before = round((float) ($previous[$id] ?? 0), 2);

            return [
                'name' => $names[$id] ?? 'Uncategorised',
                'current' => $now,
                'previous' => $before,
                'change' => $before > 0 ? (int) round(($now - $before) / $before * 100) : null,
            ];
        })->sortByDesc('current')->take(10)->values();

        return [
            'insights' => $insights,
            'comparison' => $comparison,
            'periodLabel' => sprintf(
                '%s–%s compared with %s–%s',
                $monthStart->format('j'),
                $today->format('j M Y'),
                $lastStart->format('j'),
                $cutoff->format('j M Y'),
            ),
        ];
    }

    private function pace(float $spent, float $prevSpent): array
    {
        if ($prevSpent <= 0 || $spent <= 0) {
            return [];
        }

        $change = (int) round(($spent - $prevSpent) / $prevSpent * 100);
        $now = Money::format($spent);
        $before = Money::format($prevSpent);

        if (abs($change) < 5) {
            return [$this->insight('info', 'bar-chart', "Your spending is about the same as this point last month ({$now} so far).")];
        }

        if ($change > 0) {
            return [$this->insight('warn', 'arrow-up-right', "You've spent {$now} so far, {$change}% more than the same point last month ({$before}).")];
        }

        $less = abs($change);

        return [$this->insight('good', 'arrow-down-left', "You've spent {$now} so far, {$less}% less than the same point last month ({$before}).")];
    }

    private function projection(User $user, float $spent, CarbonInterface $today): array
    {
        if ($today->day < 5 || $spent <= 0) {
            return [];
        }

        $projected = round($spent / $today->day * $today->daysInMonth);
        $text = "At this pace you'll spend about ".Money::format($projected).' this month';

        $limit = Budget::where('user_id', $user->id)->whereNull('category_id')->value('amount');

        if ($limit === null) {
            return [$this->insight('info', 'sparkles', "{$text}.")];
        }

        $limit = (float) $limit;

        if ($projected > $limit) {
            return [$this->insight(
                'warn',
                'arrow-up-right',
                "{$text}, which is ".Money::format($projected - $limit).' over your '.Money::format($limit).' monthly budget.',
                ['label' => 'Review budgets', 'url' => route('budgets.index')],
            )];
        }

        return [$this->insight('good', 'pie-chart', "{$text}, within your ".Money::format($limit).' monthly budget.')];
    }

    private function movers(Collection $ids, Collection $current, Collection $previous, array $names): array
    {
        $up = null;
        $down = null;

        foreach ($ids as $id) {
            $now = (float) ($current[$id] ?? 0);
            $before = (float) ($previous[$id] ?? 0);

            if ($before <= 0) {
                continue;
            }

            $diff = $now - $before;
            $percent = (int) round(abs($diff) / $before * 100);

            if (abs($diff) < self::MIN_CHANGE || $percent < self::MIN_PERCENT) {
                continue;
            }

            $row = ['id' => $id, 'now' => $now, 'before' => $before, 'percent' => $percent, 'diff' => $diff];

            if ($diff > 0 && (! $up || $diff > $up['diff'])) {
                $up = $row;
            }

            if ($diff < 0 && (! $down || $diff < $down['diff'])) {
                $down = $row;
            }
        }

        $insights = [];

        if ($up) {
            $name = $names[$up['id']] ?? 'Uncategorised';
            $insights[] = $this->insight(
                'warn',
                'arrow-up-right',
                "{$name} spending is up {$up['percent']}% compared with this point last month (".Money::format($up['before']).' → '.Money::format($up['now']).').',
            );
        }

        if ($down) {
            $name = $names[$down['id']] ?? 'Uncategorised';
            $insights[] = $this->insight(
                'good',
                'arrow-down-left',
                "You cut {$name} spending by {$down['percent']}% compared with this point last month (".Money::format($down['before']).' → '.Money::format($down['now']).').',
            );
        }

        return $insights;
    }

    private function biggestCategory(Collection $current, float $spent, array $names): array
    {
        if ($current->isEmpty() || $spent <= 0) {
            return [];
        }

        $topId = $current->sortDesc()->keys()->first();
        $amount = (float) $current[$topId];
        $percent = (int) round($amount / $spent * 100);
        $name = $names[$topId] ?? 'Uncategorised';

        return [$this->insight(
            'info',
            'pie-chart',
            "{$name} is your biggest category: ".Money::format($amount)." ({$percent}% of spending).",
        )];
    }

    private function savings(User $user, float $spent, CarbonInterface $monthStart, CarbonInterface $today): array
    {
        $income = round((float) $this->range($user, TransactionType::Income, $monthStart, $today)->sum('amount'), 2);

        if ($income <= 0) {
            return [];
        }

        if ($spent > $income) {
            return [$this->insight('bad', 'arrow-up-right', "You've spent ".Money::format($spent - $income).' more than you earned this month.')];
        }

        $rate = (int) round(($income - $spent) / $income * 100);

        if ($rate >= 20) {
            return [$this->insight('good', 'wallet', "You've saved {$rate}% of your income this month.")];
        }

        return [$this->insight('info', 'wallet', "You've kept only {$rate}% of your income this month.")];
    }

    private function upcoming(User $user, CarbonInterface $today): array
    {
        $items = RecurringTransaction::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('type', [TransactionType::Expense->value, TransactionType::Income->value])
            ->whereDate('next_run_on', '>=', $today->copy()->addDay()->toDateString())
            ->whereDate('next_run_on', '<=', $today->copy()->addDays(7)->toDateString())
            ->with('category')
            ->orderBy('next_run_on')
            ->limit(3)
            ->get();

        $insights = [];

        foreach ($items as $item) {
            if ($item->ends_on && $item->next_run_on->gt($item->ends_on)) {
                continue;
            }

            $days = abs((int) round($today->diffInDays($item->next_run_on->copy()->startOfDay())));
            $when = $days === 1 ? 'tomorrow' : "in {$days} days";
            $title = $item->merchant ?: ($item->category?->name ?? ucfirst($item->type->value));
            $verb = $item->type === TransactionType::Income ? 'is expected' : 'is due';

            $insights[] = $this->insight(
                'info',
                'repeat',
                "{$title}: ".Money::format((float) $item->amount)." {$verb} on ".$item->next_run_on->format('j M')." ({$when}).",
            );
        }

        return $insights;
    }

    private function largestExpense(User $user, CarbonInterface $monthStart, CarbonInterface $today): array
    {
        $query = $this->range($user, TransactionType::Expense, $monthStart, $today);

        if ((clone $query)->count() < 3) {
            return [];
        }

        $top = (clone $query)->orderByDesc('amount')->first();

        $text = 'Your largest expense this month was '.Money::format((float) $top->amount)
            .($top->merchant ? " at {$top->merchant}" : '')
            .' on '.$top->occurred_on->format('j M').'.';

        return [$this->insight('info', 'arrow-up-right', $text)];
    }

    private function uncategorised(User $user, CarbonInterface $monthStart, CarbonInterface $today): array
    {
        $count = $this->range($user, TransactionType::Expense, $monthStart, $today)->whereNull('category_id')->count();

        if ($count < 3) {
            return [];
        }

        return [$this->insight(
            'info',
            'sparkles',
            "{$count} expenses this month have no category. A smart rule can sort them automatically.",
            ['label' => 'Create a rule', 'url' => route('rules.index')],
        )];
    }

    /** @return Collection<int, float> expense totals keyed by category id (0 = uncategorised) */
    private function spendByCategory(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Expense->value)
            ->whereDate('occurred_on', '>=', $from->toDateString())
            ->whereDate('occurred_on', '<=', $to->toDateString())
            ->toBase()
            ->selectRaw('category_id, SUM(amount) AS total')
            ->groupBy('category_id')
            ->get()
            ->mapWithKeys(fn ($row) => [($row->category_id ?? 0) => round((float) $row->total, 2)]);
    }

    private function range(User $user, TransactionType $type, CarbonInterface $from, CarbonInterface $to)
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->whereDate('occurred_on', '>=', $from->toDateString())
            ->whereDate('occurred_on', '<=', $to->toDateString());
    }

    private function categoryNames(Collection $ids): array
    {
        return Category::whereIn('id', $ids->filter()->values())->pluck('name', 'id')->all()
            + [0 => 'Uncategorised'];
    }

    private function insight(string $tone, string $icon, string $text, ?array $link = null): array
    {
        return compact('tone', 'icon', 'text', 'link');
    }
}
