<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupNotificationIdempotencyKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cleanup-idempotency-keys
                            {--days=90 : Number of days to keep idempotency keys}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Force the operation to run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old notification idempotency keys to prevent unbounded growth';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $cutoff = now()->subDays($days);

        $this->info("Cleaning up notification idempotency keys older than {$days} days (before {$cutoff->toDateTimeString()})");

        // Count notifications with idempotency_key older than cutoff
        $count = DB::table('notifications')
            ->whereNotNull('idempotency_key')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            $this->info('No idempotency keys to clean up.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} notification(s) with idempotency keys older than {$days} days.");

        if ($dryRun) {
            $this->warn('Dry run mode - no records will be deleted.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Delete {$count} old idempotency key(s)?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        // Delete the idempotency keys (set to null) for old notifications
        // We don't delete the notifications themselves, just the idempotency keys
        // This allows re-sending the same notification if needed
        $deleted = DB::table('notifications')
            ->whereNotNull('idempotency_key')
            ->where('created_at', '<', $cutoff)
            ->update(['idempotency_key' => null]);

        $this->info("Successfully cleared {$deleted} idempotency key(s).");

        return self::SUCCESS;
    }
}
