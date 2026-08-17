<?php

namespace Tests\Unit\Platform;

use App\Domain\Licensing\Models\License;
use App\Domain\Monitoring\Models\BackupRecord;
use App\Domain\Platform\Services\PlatformNotificationService;
use App\Domain\Security\Models\SecurityAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PlatformNotificationServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private PlatformNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlatformNotificationService::class);
    }

    public function test_a_clean_platform_has_no_notifications(): void
    {
        $this->assertSame([], $this->service->current());
    }

    public function test_an_unresolved_critical_alert_surfaces_as_a_notification(): void
    {
        SecurityAlert::factory()->create([
            'severity' => SecurityAlert::SEVERITY_CRITICAL,
            'is_resolved' => false,
        ]);

        $items = $this->service->current();

        $this->assertCount(1, $items);
        $this->assertSame('security', $items[0]['type']);
        $this->assertSame('critical', $items[0]['severity']);
    }

    public function test_a_resolved_alert_does_not_surface(): void
    {
        SecurityAlert::factory()->create([
            'severity' => SecurityAlert::SEVERITY_CRITICAL,
            'is_resolved' => true,
        ]);

        $this->assertSame([], $this->service->current());
    }

    public function test_a_low_severity_alert_does_not_surface(): void
    {
        SecurityAlert::factory()->create([
            'severity' => SecurityAlert::SEVERITY_LOW,
            'is_resolved' => false,
        ]);

        $this->assertSame([], $this->service->current());
    }

    public function test_a_failed_backup_surfaces_as_a_notification(): void
    {
        BackupRecord::query()->create([
            'type' => BackupRecord::TYPE_DATABASE,
            'status' => BackupRecord::STATUS_FAILED,
            'started_at' => now(),
        ]);

        $items = $this->service->current();

        $this->assertCount(1, $items);
        $this->assertSame('backup', $items[0]['type']);
    }

    public function test_a_license_expiring_within_thirty_days_surfaces_as_a_notification(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        License::factory()->create([
            'business_id' => $business->id,
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        $items = $this->service->current();

        $this->assertCount(1, $items);
        $this->assertSame('license', $items[0]['type']);
    }

    public function test_a_license_expiring_far_in_the_future_does_not_surface(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        License::factory()->create([
            'business_id' => $business->id,
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addDays(90),
        ]);

        $this->assertSame([], $this->service->current());
    }
}
