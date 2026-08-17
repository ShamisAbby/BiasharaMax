<?php

namespace Tests\Unit\Backup;

use App\Domain\Backup\Services\RestoreService;
use App\Domain\Monitoring\Models\BackupRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RestoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private RestoreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RestoreService::class);
    }

    private function makeZipFixture(string $relativePath, bool $withDump = true): void
    {
        $absolutePath = Storage::disk('local')->path($relativePath);
        @mkdir(dirname($absolutePath), 0777, true);

        $zip = new ZipArchive();
        $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($withDump) {
            $sql = "CREATE TABLE businesses (id uuid);\nCREATE TABLE users (id uuid);\n";
            $zip->addFromString('db-dumps/biasharamax.sql', $sql);
        } else {
            $zip->addFromString('manifest.json', '{}');
        }

        $zip->close();
    }

    public function test_preview_inspects_a_real_zip_fixture_and_counts_tables(): void
    {
        Storage::fake('local');
        $this->makeZipFixture('backups/sample.zip');

        $record = BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'disk' => 'local',
            'file_path' => 'backups/sample.zip',
            'status' => BackupRecord::STATUS_SUCCESS,
            'started_at' => now(),
        ]);

        $preview = $this->service->preview($record);

        $this->assertSame('db-dumps/biasharamax.sql', $preview['dump_file']);
        $this->assertGreaterThan(0, $preview['size']);
        $this->assertSame(2, $preview['tables_mentioned']);
    }

    public function test_preview_throws_when_the_archive_has_no_database_dump(): void
    {
        Storage::fake('local');
        $this->makeZipFixture('backups/no-dump.zip', withDump: false);

        $record = BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'disk' => 'local',
            'file_path' => 'backups/no-dump.zip',
            'status' => BackupRecord::STATUS_SUCCESS,
            'started_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No database dump found inside this backup archive.');

        $this->service->preview($record);
    }

    /**
     * restore() has no guard clause before it shells out to the configured
     * engine's CLI client (psql/mysql) once the zip has a dump file (it
     * extracts, finds the .sql, and immediately runs Process). There is no
     * safely-testable validation path for the happy case without invoking a
     * real destructive restore, which we will not do in this suite. We only
     * verify the pre-shell-out failure path: a backup archive with no .sql
     * dump inside it.
     */
    public function test_restore_throws_when_the_archive_has_no_sql_dump_without_invoking_the_db_client(): void
    {
        Storage::fake('local');
        $this->makeZipFixture('backups/no-dump.zip', withDump: false);

        $record = BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'disk' => 'local',
            'file_path' => 'backups/no-dump.zip',
            'status' => BackupRecord::STATUS_SUCCESS,
            'started_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No .sql dump found inside this backup archive.');

        $this->service->restore($record);
    }
}
