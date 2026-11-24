<?php

namespace App\Support;

use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class TransactionFilter
{
    public const RANGES = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_30_days' => 'Last 30 days',
        'this_year' => 'This year',
        'all' => 'All time',
    ];

    public function __construct(
        public readonly string $range = 'this_month',
        public readonly ?string $type = null,
        public readonly ?int $accountId = null,
        public readonly ?int $categoryId = null,
        public readonly ?string $search = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $range = $request->query('range');
        $type = $request->query('type');
        $search = $request->query('q');

        return new self(
            range: is_string($range) && isset(self::RANGES[$range]) ? $range : 'this_month',
            type: is_string($type) ? TransactionType::tryFrom($type)?->value : null,
            accountId: self::id($request->query('account_id')),
            categoryId: self::id($request->query('category_id')),
            search: is_string($search) && trim($search) !== '' ? trim($search) : null,
        );
    }

    private static function id(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function rangeLabel(): string
    {
        return self::RANGES[$this->range];
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface}|null */
    public function dateRange(): ?array
    {
        $now = now();

        return match ($this->range) {
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => null,
        };
    }

    public function apply(Builder $query): Builder
    {
        if ($range = $this->dateRange()) {
            $query->whereDate('occurred_on', '>=', $range[0]->toDateString())
                ->whereDate('occurred_on', '<=', $range[1]->toDateString());
        }

        if ($this->type) {
            $query->where('type', $this->type);
        }

        if ($this->accountId) {
            $query->where(fn ($q) => $q
                ->where('account_id', $this->accountId)
                ->orWhere('transfer_account_id', $this->accountId));
        }

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        if ($this->search) {
            $term = '%'.$this->search.'%';

            $query->where(fn ($q) => $q
                ->whereLike('merchant', $term)
                ->orWhereLike('note', $term));
        }

        return $query;
    }
}
