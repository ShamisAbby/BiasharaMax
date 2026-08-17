<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PlatformDashboardTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_superadmin_sees_real_platform_overview(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $this->createOwnerWithBusiness();
        $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Dashboard')
                ->where('overview.total_businesses', 2)
                ->where('overview.total_superadmins', 1)
                ->has('businessRegistrationTrend', 12)
                ->has('queueSnapshot.horizon_available')
                ->where('kpis.total_businesses.value', 2)
                ->has('kpis.total_businesses.trend', 14)
                ->has('businessPulse.platform_health_score')
                ->has('businessPulse.security_score')
                ->has('liveActivity')
                ->has('revenueTrend', 12)
                ->where('platformVersion', config('app.version'))
            );
    }

    public function test_guest_cannot_view_the_dashboard(): void
    {
        $this->get(route('platform.dashboard'))
            ->assertRedirect(route('platform.login'));
    }
}
