<?php

namespace App\Domain\Backup\Console\Commands;

use App\Domain\Backup\Services\BackupService;
use App\Domain\Monitoring\Models\BackupRecord;
use Illuminate\Console\Command;

/**
 * Runs on the schedule (see routes/console.php) — daily database
 * backup, weekly full backup. Real spatie/laravel-backup runs, not a
 * simulated job.
 */
class RunScheduledBackup extends Command
{
    protected $signature = 'backup:scheduled {type=database}';

    protected $description = 'Run a scheduled platform backup and record the result.';

    public function handle(BackupService $service): int
    {
        $type = $this->argument('type');

        $record = $service->run($type, BackupRecord::TRIGGERED_SCHEDULED);

        $this->info("Scheduled {$type} backup finished with status: {$record->status}");

        return $record->status === BackupRecord::STATUS_SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
