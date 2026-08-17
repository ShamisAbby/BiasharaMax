<?php

namespace App\Domain\Backup\Services;

use App\Domain\Monitoring\Models\BackupRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Real restore against the configured database — extracts the backup zip
 * (created by spatie/laravel-backup) and pipes the plain-SQL dump it
 * contains into the engine's own CLI client (`psql` on Postgres, `mysql`
 * on MySQL/MariaDB — the dump itself is produced by spatie/laravel-backup
 * using the matching `*dump` driver for whichever engine is configured, so
 * the dump file's SQL dialect always matches the client used to restore
 * it). This is a genuinely destructive operation (it overwrites current
 * data), which is why `preview()` exists as a safe, read-only inspection
 * step before `restore()` is ever called.
 */
class RestoreService
{
    /**
     * @return array{dump_file: string, size: int, tables_mentioned: int}
     */
    public function preview(BackupRecord $record): array
    {
        $localZipPath = $this->ensureLocalCopy($record);
        $zip = new ZipArchive();
        $zip->open($localZipPath);

        $dumpEntry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'db-dumps/')) {
                $dumpEntry = $name;
                break;
            }
        }

        if (! $dumpEntry) {
            $zip->close();
            throw new \RuntimeException('No database dump found inside this backup archive.');
        }

        $stat = $zip->statName($dumpEntry);
        $contents = $zip->getFromName($dumpEntry);
        $zip->close();

        return [
            'dump_file' => $dumpEntry,
            'size' => $stat['size'] ?? 0,
            'tables_mentioned' => preg_match_all('/CREATE TABLE/i', $contents ?: ''),
        ];
    }

    /**
     * @throws \RuntimeException
     */
    public function restore(BackupRecord $record): void
    {
        $localZipPath = $this->ensureLocalCopy($record);
        $extractDir = storage_path('app/restore-tmp-'.$record->id);

        $zip = new ZipArchive();
        $zip->open($localZipPath);
        $zip->extractTo($extractDir);
        $zip->close();

        $dumpFiles = glob($extractDir.'/db-dumps/*.sql');
        if (empty($dumpFiles)) {
            $this->cleanup($extractDir);
            throw new \RuntimeException('No .sql dump found inside this backup archive.');
        }

        $driver = config('database.default');
        $connection = config('database.connections.'.$driver);
        $binaryPath = $connection['dump']['dump_binary_path'] ?? '';

        $process = match ($driver) {
            'mysql', 'mariadb' => tap(new Process([
                $binaryPath.'mysql',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--user='.$connection['username'],
                $connection['database'],
            ]), function (Process $p) use ($connection, $dumpFiles) {
                // mysql's CLI takes the dump on stdin, not a --file flag
                // (unlike psql's --file) — feed it the dump file's contents.
                // Password via MYSQL_PWD env, not --password= on the command
                // line, for the same reason PGPASSWORD is used below rather
                // than a --password flag: command-line args are visible to
                // other processes via `ps`.
                $p->setInput(fopen($dumpFiles[0], 'r'));
                $p->setEnv(['MYSQL_PWD' => $connection['password']]);
            }),
            'pgsql' => tap(new Process([
                $binaryPath.'psql',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--dbname='.$connection['database'],
                '--file='.$dumpFiles[0],
            ]), fn (Process $p) => $p->setEnv(['PGPASSWORD' => $connection['password']])),
            default => throw new \RuntimeException("Restore is not implemented for the '{$driver}' database driver."),
        };

        $process->setTimeout(300);
        $process->run();

        $this->cleanup($extractDir);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Restore failed: '.$process->getErrorOutput());
        }
    }

    private function ensureLocalCopy(BackupRecord $record): string
    {
        if ($record->disk === 'local') {
            return Storage::disk('local')->path($record->file_path);
        }

        $tempPath = storage_path('app/restore-download-'.$record->id.'.zip');
        file_put_contents($tempPath, Storage::disk($record->disk)->get($record->file_path));

        return $tempPath;
    }

    private function cleanup(string $extractDir): void
    {
        if (is_dir($extractDir)) {
            (new Process(['rm', '-rf', $extractDir]))->run();
        }
    }
}
