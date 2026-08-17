<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Monitoring\Models\BackupRecord;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;
use ZipArchive;

class BackupManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_backups_index(): void
    {
        $platformUser = PlatformUser::factory()->create();
        BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'status' => BackupRecord::STATUS_SUCCESS,
            'started_at' => now(),
        ]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.backups.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/System/Backup/Index')
                ->has('records.data', 1)
            );
    }

    public function test_platform_user_can_run_a_real_database_backup(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $response = $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.backups.run'), ['type' => BackupRecord::TYPE_DATABASE]);

        $response->assertSessionHasNoErrors();

        $record = BackupRecord::query()->latest('started_at')->first();
        $this->assertNotNull($record);
        $this->assertSame(BackupRecord::STATUS_SUCCESS, $record->status);

        // Clean up the real backup file this test produced.
        if ($record->file_path) {
            Storage::disk($record->disk)->delete($record->file_path);
        }
    }

    public function test_running_a_backup_validates_the_type(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.backups.run'), ['type' => 'not-a-real-type'])
            ->assertSessionHasErrors(['type']);
    }

    public function test_platform_user_can_destroy_a_backup_record_and_its_file(): void
    {
        Storage::fake('local');
        $appName = config('backup.backup.name', config('app.name'));
        $path = "{$appName}/2024-01-01-00-00-00.zip";
        Storage::disk('local')->put($path, 'fake-zip-contents');

        $platformUser = PlatformUser::factory()->create();
        $record = BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'disk' => 'local',
            'file_path' => $path,
            'status' => BackupRecord::STATUS_SUCCESS,
            'started_at' => now(),
        ]);

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.system.backups.destroy', $record->id))
            ->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('backup_records', ['id' => $record->id]);
    }

    public function test_platform_user_can_preview_a_backup_archive(): void
    {
        Storage::fake('local');
        $path = 'backups/sample.zip';
        $absolutePath = Storage::disk('local')->path($path);
        @mkdir(dirname($absolutePath), 0777, true);
        $zip = new ZipArchive();
        $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('db-dumps/biasharamax.sql', "CREATE TABLE businesses (id uuid);\n");
        $zip->close();

        $platformUser = PlatformUser::factory()->create();
        $record = BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'disk' => 'local',
            'file_path' => $path,
            'status' => BackupRecord::STATUS_SUCCESS,
            'started_at' => now(),
        ]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.backups.preview', $record->id))
            ->assertOk()
            ->assertJson(['dump_file' => 'db-dumps/biasharamax.sql', 'tables_mentioned' => 1]);
    }

    public function test_restore_requires_the_exact_filename_confirmation(): void
    {
        Storage::fake('local');
        $platformUser = PlatformUser::factory()->create();
        $record = BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'disk' => 'local',
            'file_path' => 'backups/2024-01-01-00-00-00.zip',
            'status' => BackupRecord::STATUS_SUCCESS,
            'started_at' => now(),
        ]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.backups.restore', $record->id), ['confirmation' => 'wrong-filename.zip'])
            ->assertSessionHasErrors(['confirmation']);
    }

    public function test_platform_admin_without_backups_manage_permission_cannot_run_a_backup(): void
    {
        $role = PlatformRole::query()->create(['name' => 'No Backups', 'slug' => 'no-backups', 'is_system' => false]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.backups.run'), ['type' => BackupRecord::TYPE_DATABASE])
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_backups(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.system.backups.index'))
            ->assertRedirect(route('platform.login'));
    }
}
