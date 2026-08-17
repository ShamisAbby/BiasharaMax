<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\RoleTemplate;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_create_a_tenant_scope_template(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $permissionIds = Permission::query()->where('scope', Permission::SCOPE_TENANT)
            ->whereIn('slug', ['products.view', 'products.create'])->pluck('id');

        $this->actingAs($platformUser, 'platform')->post(route('platform.rbac.role-templates.store'), [
            'name' => 'Inventory Clerk Template',
            'slug' => 'inventory-clerk-template',
            'scope' => 'tenant',
            'permission_ids' => $permissionIds->all(),
        ])->assertSessionHasNoErrors();

        $template = RoleTemplate::query()->where('slug', 'inventory-clerk-template')->first();
        $this->assertNotNull($template);
        $this->assertSame(2, $template->permissions()->count());
    }

    /**
     * Mirrors the platform-role fix: a tenant-scope template must not be
     * able to acquire platform-only permissions, and vice versa, since
     * `permission_ids.*` previously validated against any permission
     * regardless of the template's own `scope` field.
     */
    public function test_platform_scope_permission_cannot_be_attached_to_a_tenant_template(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $platformPermissionId = Permission::query()->where('slug', 'modules.manage')->value('id');

        $this->actingAs($platformUser, 'platform')->post(route('platform.rbac.role-templates.store'), [
            'name' => 'Leaky Template',
            'slug' => 'leaky-template',
            'scope' => 'tenant',
            'permission_ids' => [$platformPermissionId],
        ])->assertSessionHasErrors('permission_ids.0');

        $this->assertNull(RoleTemplate::query()->where('slug', 'leaky-template')->first());
    }

    public function test_tenant_scope_permission_cannot_be_attached_to_a_platform_template(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $tenantPermissionId = Permission::query()->where('slug', 'products.view')->value('id');

        $this->actingAs($platformUser, 'platform')->post(route('platform.rbac.role-templates.store'), [
            'name' => 'Leaky Platform Template',
            'slug' => 'leaky-platform-template',
            'scope' => 'platform',
            'permission_ids' => [$tenantPermissionId],
        ])->assertSessionHasErrors('permission_ids.0');
    }

    public function test_system_template_cannot_be_deleted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $template = RoleTemplate::factory()->create(['is_system' => true]);

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.rbac.role-templates.destroy', $template->id))
            ->assertSessionHasErrors('role_template');

        $this->assertNotNull($template->fresh());
    }

    public function test_non_system_template_can_be_deleted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $template = RoleTemplate::factory()->create(['is_system' => false]);

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.rbac.role-templates.destroy', $template->id))
            ->assertSessionHasNoErrors();

        $this->assertNull(RoleTemplate::find($template->id));
    }
}
