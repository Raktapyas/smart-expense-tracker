<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'user_id', 'name', 'type', 'opening_balance',
        'currency', 'color', 'icon', 'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'opening_balance' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    /** Adds the summed transaction columns used by the `balance` attribute. */
    public function scopeWithBalance(Builder $query): Builder
    {
        $sum = fn (string $type, string $column) => Transaction::query()
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn($column, 'accounts.id')
            ->where('type', $type);

        return $query
            ->select('accounts.*')
            ->selectSub($sum('income', 'account_id'), 'income_total')
            ->selectSub($sum('expense', 'account_id'), 'expense_total')
            ->selectSub($sum('transfer', 'account_id'), 'transfer_out_total')
            ->selectSub($sum('transfer', 'transfer_account_id'), 'transfer_in_total');
    }

    /** Only correct for accounts loaded with withBalance(). */
    protected function balance(): Attribute
    {
        return Attribute::get(fn () => round(
            (float) $this->opening_balance
            + (float) ($this->income_total ?? 0)
            - (float) ($this->expense_total ?? 0)
            - (float) ($this->transfer_out_total ?? 0)
            + (float) ($this->transfer_in_total ?? 0),
            2,
        ));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
