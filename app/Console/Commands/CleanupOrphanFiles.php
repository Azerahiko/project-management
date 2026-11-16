<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanFiles extends Command
{
    protected $signature = 'files:cleanup-orphans
                            {--dry-run : Show orphaned files without deleting them}
                            {--force : Delete without confirmation}';

    protected $description = 'Delete physical project files that no longer have a database record';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $referenced = File::withTrashed()->pluck('storage_path')->flip();
        $cutoff = now()->subHour()->getTimestamp();
        $orphans = [];

        foreach ($disk->allFiles('project-files') as $path) {
            if ($referenced->has($path) || $disk->lastModified($path) > $cutoff) {
                continue;
            }

            $orphans[] = $path;
        }

        if ($orphans === []) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        $this->info(count($orphans).' orphaned file(s) found.');

        if ($this->option('dry-run')) {
            $this->line(implode(PHP_EOL, $orphans));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete these orphaned files?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $disk->delete($orphans);
        $this->info(count($orphans).' orphaned file(s) deleted.');

        return self::SUCCESS;
    }
}
