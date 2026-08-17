<?php

namespace Tests\Unit\Backup;

use App\Domain\Backup\Services\BackupService;
use App\Domain\Monitoring\Models\BackupRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BackupService::class);
    }

    /**
     * Runs a genuine `backup:run --only-db` against the small test
     * database — fast (well under a second) and read-only from the
     * database's perspective, so it is safe to exercise for real
     * rather than mocking the Artisan command.
     */
    public function test_run_creates_a_backup_record_with_real_pg_dump_output(): void
    {
        $record = $this->service->run(BackupRecord::TYPE_DATABASE, BackupRecord::TRIGGERED_MANUAL);

        $this->assertSame(BackupRecord::STATUS_SUCCESS, $record->status);
        $this->assertSame(BackupRecord::TRIGGERED_MANUAL, $record->triggered_by);
        $this->assertSame('local', $record->disk);
        $this->assertNotNull($record->file_path);
        $this->assertNotNull($record->file_size);
        $this->assertNotNull($record->completed_at);

        $this->assertDatabaseHas('backup_records', [
            'id' => $record->id,
            'status' => BackupRecord::STATUS_SUCCESS,
            'triggered_by' => BackupRecord::TRIGGERED_MANUAL,
        ]);

        // Clean up the real backup file this test produced.
        Storage::disk($record->disk)->delete($record->file_path);
    }

    public function test_list_backup_files_lists_zip_files_on_disk(): void
    {
        Storage::fake('local');
        $appName = config('backup.backup.name', config('app.name'));
        Storage::disk('local')->put("{$appName}/2024-01-01-00-00-00.zip", 'fake-zip-contents');
        Storage::disk('local')->put("{$appName}/not-a-backup.txt", 'irrelevant');

        $files = $this->service->listBackupFiles('local');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.zip', $files[0]['path']);
        $this->assertArrayHasKey('size', $files[0]);
        $this->assertArrayHasKey('date', $files[0]);
    }

    public function test_delete_removes_the_file_and_the_record(): void
    {
        Storage::fake('local');
        $appName = config('backup.backup.name', config('app.name'));
        $path = "{$appName}/2024-01-01-00-00-00.zip";
        Storage::disk('local')->put($path, 'fake-zip-contents');

        $record = BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'disk' => 'local',
            'file_path' => $path,
            'status' => BackupRecord::STATUS_SUCCESS,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->service->delete($record);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('backup_records', ['id' => $record->id]);
    }
}
