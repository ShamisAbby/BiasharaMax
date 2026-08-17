<?php

namespace App\Domain\Backup\Services;

use App\Domain\Monitoring\Models\BackupRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Thin wrapper around spatie/laravel-backup's real `backup:run` /
 * `backup:list` commands — this is genuine database + file backup
 * (zip archive on the configured disk), not a simulated process.
 * `backup_records` (built in the Platform Operations sprint) logs
 * every real run for the dashboard/history view.
 */
class BackupService
{
    public function run(string $type = BackupRecord::TYPE_FULL, string $triggeredBy = BackupRecord::TRIGGERED_MANUAL): BackupRecord
    {
        $record = BackupRecord::query()->create([
            'type' => $type,
            'disk' => config('backup.backup.destination.disks.0', 'local'),
            'triggered_by' => $triggeredBy,
            'status' => BackupRecord::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $options = ['--disable-notifications' => true];
        if ($type === BackupRecord::TYPE_DATABASE) {
            $options['--only-db'] = true;
        } elseif ($type === BackupRecord::TYPE_STORAGE) {
            $options['--only-files'] = true;
        }

        try {
            $exitCode = Artisan::call('backup:run', $options);
            $latest = $this->latestBackupFile($record->disk);

            $record->update([
                'status' => $exitCode === 0 ? BackupRecord::STATUS_SUCCESS : BackupRecord::STATUS_FAILED,
                'file_path' => $latest['path'] ?? null,
                'file_size' => $latest['size'] ?? null,
                'completed_at' => now(),
                'error_message' => $exitCode === 0 ? null : 'backup:run exited with a non-zero status.',
            ]);
        } catch (\Throwable $e) {
            $record->update([
                'status' => BackupRecord::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }

        return $record->refresh();
    }

    /**
     * @return array<int, array{path: string, size: int, date: \Illuminate\Support\Carbon}>
     */
    public function listBackupFiles(string $disk = 'local'): array
    {
        $appName = config('backup.backup.name', config('app.name'));
        $files = Storage::disk($disk)->allFiles($appName);

        return collect($files)
            ->filter(fn ($file) => str_ends_with($file, '.zip'))
            ->map(fn ($file) => [
                'path' => $file,
                'size' => Storage::disk($disk)->size($file),
                'date' => \Illuminate\Support\Carbon::createFromTimestamp(Storage::disk($disk)->lastModified($file)),
            ])
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function delete(BackupRecord $record): void
    {
        if ($record->file_path && Storage::disk($record->disk)->exists($record->file_path)) {
            Storage::disk($record->disk)->delete($record->file_path);
        }

        $record->delete();
    }

    /**
     * @return array{path: ?string, size: ?int}
     */
    private function latestBackupFile(string $disk): array
    {
        $files = $this->listBackupFiles($disk);

        return $files[0] ?? ['path' => null, 'size' => null];
    }
}
