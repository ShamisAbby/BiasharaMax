<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Models\BlockedIp;
use App\Domain\Security\Models\SecurityAlert;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SecurityCenterManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_the_security_dashboard(): void
    {
        $platformUser = PlatformUser::factory()->create();
        BlockedIp::query()->create(['ip_address' => '10.0.0.1']);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.operations.security.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Operations/Security/Index')
                ->where('summary.blocked_ips_count', 1)
            );
    }

    public function test_platform_user_can_block_and_unblock_an_ip(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.security.blocked-ips.store'), [
            'ip_address' => '203.0.113.5',
            'reason' => 'Repeated brute force attempts',
        ])->assertSessionHasNoErrors();

        $ip = BlockedIp::query()->where('ip_address', '203.0.113.5')->first();
        $this->assertNotNull($ip);

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.operations.security.blocked-ips.destroy', $ip->id))
            ->assertSessionHasNoErrors();

        $this->assertNull(BlockedIp::find($ip->id));
    }

    public function test_platform_user_can_unlock_an_account(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $lockout = AccountLockout::query()->create([
            'lockable_type' => AccountLockout::TYPE_PLATFORM_USER,
            'lockable_id' => PlatformUser::factory()->create()->id,
            'locked_at' => now(),
        ]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.security.lockouts.unlock', $lockout->id))
            ->assertSessionHasNoErrors();

        $this->assertFalse($lockout->fresh()->isActive());
    }

    public function test_platform_user_can_resolve_a_security_alert(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $alert = SecurityAlert::factory()->create(['is_resolved' => false]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.security.alerts.resolve', $alert->id))
            ->assertSessionHasNoErrors();

        $this->assertTrue($alert->fresh()->is_resolved);
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_platform_admin_without_manage_permission_cannot_block_an_ip(): void
    {
        $role = \App\Domain\RBAC\Models\PlatformRole::query()->create(['name' => 'Viewer', 'slug' => 'security-viewer', 'is_system' => false]);
        $role->permissions()->sync(
            \App\Domain\RBAC\Models\Permission::query()->where('slug', 'security.view')->pluck('id'),
        );
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.security.blocked-ips.store'), ['ip_address' => '1.2.3.4'])
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_security_center(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.operations.security.index'))
            ->assertRedirect(route('platform.login'));
    }
}
