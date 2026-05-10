<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-notifications {--days=30 : The number of days to keep notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune database notifications older than a specified number of days.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $this->info("Pruning notifications older than {$days} days ({$cutoff->toDateTimeString()})...");

        $count = 0;

        // Delete in chunks of 1000 to prevent long table locks in high-volume environments
        do {
            $deleted = DatabaseNotification::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $count += $deleted;

            if ($deleted > 0) {
                $this->comment("Deleted {$deleted} rows... Total: {$count}");
            }
        } while ($deleted > 0);

        $this->info("Successfully pruned {$count} notifications.");

        return self::SUCCESS;
    }
}
