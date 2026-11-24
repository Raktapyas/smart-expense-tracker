<?php

namespace App\Models;

use App\Enums\Frequency;
use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'user_id', 'account_id', 'transfer_account_id', 'category_id', 'type', 'amount',
        'merchant', 'note', 'frequency', 'starts_on', 'next_run_on', 'ends_on',
        'last_run_on', 'run_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'frequency' => Frequency::class,
            'amount' => 'decimal:2',
            'starts_on' => 'date',
            'next_run_on' => 'date',
            'ends_on' => 'date',
            'last_run_on' => 'date',
            'run_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Date of the n-th occurrence (0 = the start date). Month ends are clamped, never overflowed. */
    public function occurrence(int $n): CarbonImmutable
    {
        $start = CarbonImmutable::parse($this->starts_on->toDateString());

        return match ($this->frequency) {
            Frequency::Weekly => $start->addWeeks($n),
            Frequency::Yearly => $start->addYearsNoOverflow($n),
            default => $start->addMonthsNoOverflow($n),
        };
    }

    /** Moves next_run_on to the first occurrence on or after $today without creating anything. */
    public function skipMissed(CarbonInterface $today): void
    {
        $guard = 0;

        while ($this->next_run_on->toDateString() < $today->toDateString() && $guard++ < 5000) {
            $this->run_count++;
            $this->next_run_on = $this->occurrence($this->run_count);
        }
    }

    public function statusLabel(): string
    {
        if ($this->is_active) {
            return 'Active';
        }

        return $this->ends_on && $this->next_run_on->gt($this->ends_on) ? 'Ended' : 'Paused';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
