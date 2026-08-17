<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class LicenseManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_superadmin_can_generate_a_license(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($platformUser, 'platform')->post(route('platform.licenses.store'), [
            'business_id' => $business->id,
            'type' => 'professional',
            'max_devices' => 3,
            'offline_activation_allowed' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('licenses', ['business_id' => $business->id, 'type' => 'professional']);
    }

    /**
     * The rule that made the Generate button look broken.
     *
     * `expires_at` is validated `after:today`, so today — the date a picker
     * lands on first — is rejected. The rule itself is defensible: a
     * licence that expires today is expired the moment it is issued.
     *
     * What was not defensible is that the form rendered *only*
     * `errors.business_id`. So this rejection came back, populated an
     * errors bag nothing was reading, and the modal sat there looking
     * inert. From the admin's side the button did nothing at all.
     *
     * Pinning the error to a specific key is the part worth asserting —
     * it's what keeps that field's message in the form honest.
     */
    public function test_an_expiry_of_today_is_rejected_against_the_expires_at_field(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($platformUser, 'platform')
            ->from(route('platform.licenses.index'))
            ->post(route('platform.licenses.store'), [
                'business_id' => $business->id,
                'type' => 'professional',
                'max_devices' => 3,
                'expires_at' => now()->toDateString(),
            ]);

        $response->assertSessionHasErrors('expires_at');
        $this->assertDatabaseCount('licenses', 0);
    }

    public function test_a_future_expiry_is_accepted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($platformUser, 'platform')->post(route('platform.licenses.store'), [
            'business_id' => $business->id,
            'type' => 'professional',
            'max_devices' => 3,
            'expires_at' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('licenses', 1);
    }

    /**
     * The field is labelled "blank = never", and the form submits an empty
     * string rather than omitting the key — so this asserts the label is
     * true for what the UI actually sends, not for a hand-built payload.
     */
    public function test_a_blank_expiry_generates_a_licence_that_never_expires(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($platformUser, 'platform')->post(route('platform.licenses.store'), [
            'business_id' => $business->id,
            'type' => 'professional',
            'max_devices' => 3,
            'expires_at' => '',
            'maintenance_expires_at' => '',
            'notes' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('licenses', [
            'business_id' => $business->id,
            'expires_at' => null,
        ]);
    }

    public function test_superadmin_can_view_license_details_with_devices_and_history(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $license = app(LicenseService::class)->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);
        app(LicenseService::class)->activate($license, 'FINGERPRINT-A', 'Test PC');

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.licenses.show', $license->id))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Licenses/Show')
                ->where('license.license_key', $license->license_key)
                ->has('devices', 1)
                ->has('activationLogs')
            );
    }

    public function test_superadmin_can_suspend_and_restore_a_license(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $license = app(LicenseService::class)->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.licenses.suspend', $license->id))
            ->assertRedirect();
        $this->assertSame(License::STATUS_SUSPENDED, $license->fresh()->status);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.licenses.restore', $license->id))
            ->assertRedirect();
        $this->assertSame(License::STATUS_ACTIVE, $license->fresh()->status);
    }

    public function test_superadmin_can_revoke_a_license_with_a_reason(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $license = app(LicenseService::class)->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.licenses.revoke', $license->id), ['reason' => 'Fraudulent purchase'])
            ->assertRedirect();

        $fresh = $license->fresh();
        $this->assertSame(License::STATUS_REVOKED, $fresh->status);
        $this->assertSame('Fraudulent purchase', $fresh->revoked_reason);
    }

    public function test_superadmin_can_download_an_offline_certificate(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $license = app(LicenseService::class)->generate(['business_id' => $business->id, 'type' => License::TYPE_STARTER, 'max_devices' => 1]);

        $response = $this->actingAs($platformUser, 'platform')
            ->get(route('platform.licenses.certificate', $license->id));

        $response->assertOk();
        $this->assertNotEmpty($response->getContent());
    }

    public function test_tenant_user_cannot_access_license_management(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.licenses.dashboard'))
            ->assertRedirect(route('platform.login'));
    }
}
