<?php

namespace App\Services;

use App\Actions\SaveTransaction;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RecurringTransactionRunner
{
    public function __construct(private SaveTransaction $save) {}

    /** Creates every due transaction for one user. Returns how many were created. */
    public function runFor(User $user, ?CarbonInterface $today = null): int
    {
        return $this->process(RecurringTransaction::query()->where('user_id', $user->id), $today);
    }

    /** Creates every due transaction for all users. Returns how many were created. */
    public function runAll(?CarbonInterface $today = null): int
    {
        return $this->process(RecurringTransaction::query(), $today);
    }

    private function process(Builder $query, ?CarbonInterface $today): int
    {
        $today = ($today ?? now())->toDateString();

        return $query
            ->where('is_active', true)
            ->whereDate('next_run_on', '<=', $today)
            ->with('user')
            ->get()
            ->sum(fn (RecurringTransaction $recurring) => DB::transaction(fn () => $this->catchUp($recurring, $today)));
    }

    private function catchUp(RecurringTransaction $recurring, string $today): int
    {
        $created = 0;
        $guard = 0;

        while ($recurring->is_active && $recurring->next_run_on->toDateString() <= $today && $guard++ < 366) {
            $date = $recurring->next_run_on;

            if ($recurring->ends_on && $date->gt($recurring->ends_on)) {
                $recurring->is_active = false;
                break;
            }

            $alreadyExists = Transaction::withTrashed()
                ->where('recurring_transaction_id', $recurring->id)
                ->whereDate('occurred_on', $date->toDateString())
                ->exists();

            if (! $alreadyExists) {
                $transaction = ($this->save)($recurring->user, [
                    'type' => $recurring->type->value,
                    'account_id' => $recurring->account_id,
                    'transfer_account_id' => $recurring->transfer_account_id,
                    'category_id' => $recurring->category_id,
                    'amount' => $recurring->amount,
                    'occurred_on' => $date->toDateString(),
                    'merchant' => $recurring->merchant,
                    'note' => $recurring->note,
                ]);

                $transaction->forceFill(['recurring_transaction_id' => $recurring->id])->save();
                $created++;
            }

            $recurring->last_run_on = $date;
            $recurring->run_count++;
            $recurring->next_run_on = $recurring->occurrence($recurring->run_count);
        }

        if ($recurring->is_active && $recurring->ends_on && $recurring->next_run_on->gt($recurring->ends_on)) {
            $recurring->is_active = false;
        }

        $recurring->save();

        return $created;
    }
}
