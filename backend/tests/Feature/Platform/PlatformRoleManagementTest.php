<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\PlatformRole;
use App\Domain\RBAC\Models\RoleTemplate;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_create_a_role_with_platform_scope_permissions(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $permissionIds = Permission::query()->where('scope', Permission::SCOPE_PLATFORM)
            ->whereIn('slug', ['business_types.view', 'modules.view'])->pluck('id');

        $this->actingAs($platformUser, 'platform')->post(route('platform.rbac.platform-roles.store'), [
            'name' => 'Catalog Manager',
            'slug' => 'catalog-manager',
            'permission_ids' => $permissionIds->all(),
        ])->assertSessionHasNoErrors();

        $role = PlatformRole::query()->where('slug', 'catalog-manager')->first();
        $this->assertNotNull($role);
        $this->assertSame(2, $role->permissions()->count());
    }

    /**
     * This is the exact scope-leak this sprint's fix closes: before the
     * fix, `permission_ids.*` validated against `exists:permissions,id`
     * with no scope filter, so a tenant-only permission (e.g.
     * `products.view`) could be attached to a platform role.
     */
    public function test_tenant_scope_permission_cannot_be_attached_to_a_platform_role(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $tenantPermissionId = Permission::query()->where('slug', 'products.view')->value('id');

        $this->actingAs($platformUser, 'platform')->post(route('platform.rbac.platform-roles.store'), [
            'name' => 'Leaky Role',
            'slug' => 'leaky-role',
            'permission_ids' => [$tenantPermissionId],
        ])->assertSessionHasErrors('permission_ids.0');

        $this->assertNull(PlatformRole::query()->where('slug', 'leaky-role')->first());
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $superAdmin = PlatformRole::query()->where('slug', PlatformRole::SUPER_ADMIN)->first();

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.rbac.platform-roles.destroy', $superAdmin->id))
            ->assertSessionHasErrors('platform_role');

        $this->assertNotNull($superAdmin->fresh());
    }

    public function test_role_assigned_to_users_cannot_be_deleted(): void
    {
        $platformAdminRole = PlatformRole::query()->where('slug', PlatformRole::PLATFORM_ADMIN)->first();
        $platformAdminRole->update(['is_system' => false]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $platformAdminRole->id]);
        $superAdmin = PlatformUser::factory()->create();

        $this->actingAs($superAdmin, 'platform')
            ->delete(route('platform.rbac.platform-roles.destroy', $platformAdminRole->id))
            ->assertSessionHasErrors('platform_role');

        $this->assertNotNull($platformAdminRole->fresh());
        $this->assertNotNull($platformUser);
    }

    public function test_cloning_a_platform_role_copies_its_permissions(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $superAdmin = PlatformRole::query()->where('slug', PlatformRole::SUPER_ADMIN)->first();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.rbac.platform-roles.clone', $superAdmin->id), ['name' => 'Super Admin Copy'])
            ->assertSessionHasNoErrors();

        $clone = PlatformRole::query()->where('name', 'Super Admin Copy')->first();
        $this->assertNotNull($clone);
        $this->assertFalse($clone->is_system);
        $this->assertSame($superAdmin->permissions()->count(), $clone->permissions()->count());
    }

    public function test_applying_a_template_replaces_a_roles_permissions(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $role = PlatformRole::query()->create(['name' => 'Blank Role', 'slug' => 'blank-role', 'is_system' => false]);
        $template = RoleTemplate::factory()->create(['scope' => 'platform']);
        $template->permissions()->sync(
            Permission::query()->where('scope', Permission::SCOPE_PLATFORM)->limit(3)->pluck('id'),
        );

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.rbac.platform-roles.apply-template', $role->id), ['role_template_id' => $template->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $role->fresh()->permissions()->count());
    }

    public function test_platform_admin_excluded_permissions_are_not_granted(): void
    {
        $platformAdmin = PlatformRole::query()->where('slug', PlatformRole::PLATFORM_ADMIN)->first();

        $this->assertFalse($platformAdmin->permissions()->where('slug', 'platform_roles.manage')->exists());
        $this->assertTrue($platformAdmin->permissions()->where('slug', 'business_types.manage')->exists());
    }
}
