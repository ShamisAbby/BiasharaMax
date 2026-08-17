<?php

namespace Tests\Unit\Licensing;

use App\Domain\Licensing\Exceptions\LicenseException;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private LicenseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LicenseService::class);
    }

    public function test_generate_creates_a_license_with_a_unique_formatted_key(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $license = $this->service->generate([
            'business_id' => $business->id,
            'type' => License::TYPE_PROFESSIONAL,
            'max_devices' => 3,
        ]);

        $this->assertMatchesRegularExpression('/^BMAX-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license->license_key);
        $this->assertSame(License::STATUS_ACTIVE, $license->status);
        $this->assertDatabaseHas('license_activation_logs', [
            'license_id' => $license->id,
            'action' => 'generated',
        ]);
    }

    /**
     * The rename must not strand anybody.
     *
     * Keys issued before the change begin `BIOS-`, and every customer
     * holding one has it typed into a till. Activation and validation
     * look the key up by exact match, so nothing should care about the
     * prefix — but "should" is what this test is for. If someone later
     * adds a format check to tidy things up, this fails rather than a
     * shop discovering it at opening time.
     */
    public function test_a_key_issued_under_the_old_prefix_still_activates(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $license = License::factory()->create([
            'business_id' => $business->id,
            'license_key' => 'BIOS-OLD1-OLD2-OLD3-OLD4',
            'status' => License::STATUS_ACTIVE,
        ]);

        $device = $this->service->activate($license, 'legacy-machine', 'Front Counter');

        $this->assertSame($license->id, $device->license_id);
        $this->assertTrue(
            $this->service->validate('BIOS-OLD1-OLD2-OLD3-OLD4', 'legacy-machine')['valid'],
        );
    }

    public function test_activate_registers_a_new_device(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = $this->service->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 2]);

        $device = $this->service->activate($license, 'FINGERPRINT-A', 'Office PC');

        $this->assertSame('FINGERPRINT-A', $device->hardware_fingerprint);
        $this->assertTrue($device->isActive());
    }

    public function test_activate_throws_once_device_limit_is_reached(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = $this->service->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);
        $this->service->activate($license, 'FINGERPRINT-A');

        $this->expectException(LicenseException::class);
        $this->service->activate($license, 'FINGERPRINT-B');
    }

    public function test_reactivating_the_same_fingerprint_does_not_consume_another_device_slot(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = $this->service->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);
        $device = $this->service->activate($license, 'FINGERPRINT-A');
        $this->service->deactivateDevice($license, $device);

        $reactivated = $this->service->activate($license, 'FINGERPRINT-A');

        $this->assertSame($device->id, $reactivated->id);
        $this->assertTrue($reactivated->fresh()->isActive());
    }

    public function test_activate_fails_for_a_suspended_license(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = $this->service->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);
        $this->service->suspend($license);

        $this->expectException(LicenseException::class);
        $this->service->activate($license, 'FINGERPRINT-A');
    }

    public function test_validate_succeeds_for_an_activated_device(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = $this->service->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);
        $this->service->activate($license, 'FINGERPRINT-A');

        $result = $this->service->validate($license->license_key, 'FINGERPRINT-A');

        $this->assertTrue($result['valid']);
    }

    public function test_validate_fails_for_an_unknown_device(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = $this->service->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);

        $result = $this->service->validate($license->license_key, 'NEVER-ACTIVATED');

        $this->assertFalse($result['valid']);
    }

    public function test_reset_activation_deactivates_every_device(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = $this->service->generate(['business_id' => $business->id, 'type' => License::TYPE_PROFESSIONAL, 'max_devices' => 3]);
        $this->service->activate($license, 'FINGERPRINT-A');
        $this->service->activate($license, 'FINGERPRINT-B');

        $this->service->resetActivation($license);

        $this->assertSame(0, $license->activeDevices()->count());
    }

    public function test_revoke_deactivates_all_devices_and_marks_the_license_revoked(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = $this->service->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);
        $this->service->activate($license, 'FINGERPRINT-A');

        $this->service->revoke($license, 'Chargeback');

        $fresh = $license->fresh();
        $this->assertSame(License::STATUS_REVOKED, $fresh->status);
        $this->assertSame('Chargeback', $fresh->revoked_reason);
        $this->assertSame(0, $fresh->activeDevices()->count());
    }
}
