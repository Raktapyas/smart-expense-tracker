<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionRunner;
use Illuminate\Console\Command;

class RunRecurringTransactions extends Command
{
    protected $signature = 'recurring:run';

    protected $description = 'Create transactions for all recurring schedules that are due';

    public function handle(RecurringTransactionRunner $runner): int
    {
        $created = $runner->runAll();

        $this->info("Created {$created} transaction(s).");

        return self::SUCCESS;
    }
}
