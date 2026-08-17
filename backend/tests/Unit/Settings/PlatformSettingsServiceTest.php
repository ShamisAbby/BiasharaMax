<?php

namespace Tests\Unit\Settings;

use App\Domain\Settings\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlatformSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlatformSettingsService::class);
    }

    public function test_all_grouped_returns_every_schema_group_with_defaults(): void
    {
        $grouped = $this->service->allGrouped();

        $this->assertCount(9, PlatformSettingsService::SCHEMA);
        foreach (array_keys(PlatformSettingsService::SCHEMA) as $group) {
            $this->assertArrayHasKey($group, $grouped);
        }

        $this->assertSame('BiasharaMax', $grouped['general']['platform_name']);
        $this->assertSame('TZS', $grouped['payment']['global_currency']);
    }

    public function test_update_group_persists_whitelisted_keys(): void
    {
        $this->service->updateGroup('general', ['platform_name' => 'My Platform']);

        $grouped = $this->service->allGrouped();

        $this->assertSame('My Platform', $grouped['general']['platform_name']);
    }

    public function test_update_group_ignores_keys_not_present_in_the_schema(): void
    {
        $this->service->updateGroup('general', ['platform_name' => 'My Platform', 'not_a_real_setting' => 'should-be-ignored']);

        $this->assertDatabaseMissing('platform_settings', ['key' => 'general.not_a_real_setting']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'general.platform_name']);
    }

    public function test_update_group_throws_for_an_unknown_group(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->updateGroup('not-a-real-group', ['foo' => 'bar']);
    }

    public function test_store_uploaded_file_stores_to_the_public_disk_and_returns_a_url(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png');

        $url = $this->service->storeUploadedFile($file);

        $this->assertStringContainsString('/storage/branding/', $url);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));
    }
}
