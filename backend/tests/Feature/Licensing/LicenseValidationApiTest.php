<?php

namespace Tests\Feature\Licensing;

use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Exercises the unauthenticated REST surface a real desktop client would
 * call. No such client exists yet in this codebase — these tests stand
 * in for it, proving the server side is genuinely functional.
 */
class LicenseValidationApiTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_activate_endpoint_registers_a_device(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = app(LicenseService::class)->generate([
            'business_id' => $business->id,
            'type' => License::TYPE_STARTER,
            'max_devices' => 1,
        ]);

        $response = $this->postJson('/api/v1/licenses/activate', [
            'license_key' => $license->license_key,
            'hardware_fingerprint' => 'DESKTOP-FP-1',
            'machine_name' => "Owner's PC",
        ]);

        $response->assertOk();
        $response->assertJson(['activated' => true]);
        $this->assertDatabaseHas('license_devices', [
            'license_id' => $license->id,
            'hardware_fingerprint' => 'DESKTOP-FP-1',
        ]);
    }

    public function test_activate_endpoint_rejects_an_unknown_license_key(): void
    {
        $response = $this->postJson('/api/v1/licenses/activate', [
            'license_key' => 'BIOS-FAKE-FAKE-FAKE-FAKE',
            'hardware_fingerprint' => 'DESKTOP-FP-1',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['activated' => false]);
    }

    public function test_activate_endpoint_rejects_a_device_beyond_the_limit(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = app(LicenseService::class)->generate([
            'business_id' => $business->id,
            'type' => License::TYPE_STARTER,
            'max_devices' => 1,
        ]);
        app(LicenseService::class)->activate($license, 'DESKTOP-FP-1');

        $response = $this->postJson('/api/v1/licenses/activate', [
            'license_key' => $license->license_key,
            'hardware_fingerprint' => 'DESKTOP-FP-2',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['activated' => false]);
    }

    public function test_validate_endpoint_confirms_an_activated_device(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = app(LicenseService::class)->generate([
            'business_id' => $business->id,
            'type' => License::TYPE_PROFESSIONAL,
            'max_devices' => 1,
        ]);
        app(LicenseService::class)->activate($license, 'DESKTOP-FP-1');

        $response = $this->postJson('/api/v1/licenses/validate', [
            'license_key' => $license->license_key,
            'hardware_fingerprint' => 'DESKTOP-FP-1',
        ]);

        $response->assertOk();
        $response->assertJson(['valid' => true, 'type' => License::TYPE_PROFESSIONAL]);
    }

    public function test_validate_endpoint_rejects_an_unactivated_device(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $license = app(LicenseService::class)->generate([
            'business_id' => $business->id,
            'type' => License::TYPE_STARTER,
            'max_devices' => 1,
        ]);

        $response = $this->postJson('/api/v1/licenses/validate', [
            'license_key' => $license->license_key,
            'hardware_fingerprint' => 'NEVER-ACTIVATED',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['valid' => false]);
    }
}
