<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Models\ImpersonationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_superadmin_can_impersonate_a_business_owner(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.businesses.impersonate', $business->id))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($owner, 'web');

        $this->assertDatabaseHas('impersonation_logs', [
            'platform_user_id' => $platformUser->id,
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'ended_at' => null,
        ]);
    }

    public function test_impersonation_banner_appears_for_the_impersonated_session(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.businesses.impersonate', $business->id));

        // A real browser request always resolves the default ('web') guard
        // fresh; only PHPUnit's actingAs() leaves 'platform' as the
        // process-wide default guard after the call above.
        auth()->shouldUse('web');

        $this->get(route('dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->where('impersonating', true)
            );
    }

    public function test_stopping_impersonation_marks_the_log_ended_and_returns_to_platform(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.businesses.impersonate', $business->id));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('platform.dashboard'));

        $this->assertGuest('web');

        $log = ImpersonationLog::query()->where('business_id', $business->id)->first();
        $this->assertNotNull($log->ended_at);
    }

    public function test_tenant_owner_cannot_trigger_impersonation_routes(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->post(route('platform.businesses.impersonate', $business->id))
            ->assertRedirect(route('platform.login'));
    }
}
