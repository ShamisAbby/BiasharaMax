<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SystemDashboardTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_the_system_dashboard(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/System/Dashboard')
                ->has('integrationStatus.total')
                ->has('paymentStatus')
            );
    }

    public function test_guest_cannot_view_the_system_dashboard(): void
    {
        $this->get(route('platform.system.dashboard'))
            ->assertRedirect(route('platform.login'));
    }

    public function test_tenant_user_cannot_view_the_system_dashboard(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.system.dashboard'))
            ->assertRedirect(route('platform.login'));
    }
}
