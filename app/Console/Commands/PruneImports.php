<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneImports extends Command
{
    protected $signature = 'imports:prune';

    protected $description = 'Delete uploaded import files older than 24 hours';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDay()->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('imports') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} old import file(s).");

        return self::SUCCESS;
    }
}
