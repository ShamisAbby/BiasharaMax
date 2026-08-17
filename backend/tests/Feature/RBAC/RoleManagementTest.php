<?php

namespace Tests\Feature\RBAC;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\BusinessRegistrationService;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\Role;
use App\Domain\RBAC\Models\RoleTemplate;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_view_roles_index(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->get('/settings/roles')->assertOk();
    }

    public function test_owner_can_create_a_custom_role_with_selected_permissions(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $permissionIds = Permission::query()->whereIn('slug', ['dashboard.view', 'employees.view'])->pluck('id');

        $this->actingAs($owner)->post('/settings/roles', [
            'name' => 'Stock Clerk',
            'description' => 'Limited dashboard and employee visibility.',
            'permissions' => $permissionIds->all(),
        ])->assertSessionHasNoErrors();

        $role = Role::query()->where('business_id', $business->getKey())->where('name', 'Stock Clerk')->first();
        $this->assertNotNull($role);
        $this->assertFalse($role->is_system);
        $this->assertSame(2, $role->permissions()->count());
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $ownerRole = Role::query()->where('business_id', $business->getKey())->where('slug', Role::OWNER)->first();

        $this->actingAs($owner)
            ->delete("/settings/roles/{$ownerRole->getKey()}")
            ->assertForbidden();

        $this->assertNotNull($ownerRole->fresh());
    }

    public function test_custom_roles_can_be_deleted_by_authorized_user(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $customRole = Role::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'Temp Role',
            'slug' => 'temp-role',
            'is_system' => false,
        ]);

        $this->actingAs($owner)
            ->delete("/settings/roles/{$customRole->getKey()}")
            ->assertSessionHasNoErrors();

        $this->assertNull($customRole->fresh());
    }

    /**
     * Role::class applies the tenant global scope, so a cross-tenant route
     * binding never resolves: it 404s before the RolePolicy is even
     * consulted. That is stronger isolation than a 403 would be, since the
     * response never confirms the role exists at all.
     */
    public function test_role_from_another_business_cannot_be_updated(): void
    {
        [$ownerA] = $this->createOwnerWithBusiness();
        [, $businessB] = $this->createOwnerWithBusiness();

        $roleInB = Role::query()->where('business_id', $businessB->getKey())->where('slug', Role::MANAGER)->first();

        $this->actingAs($ownerA)
            ->patch("/settings/roles/{$roleInB->getKey()}", [
                'name' => 'Hijacked',
                'permissions' => [],
            ])
            ->assertNotFound();
    }

    public function test_owner_can_clone_a_role(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $managerRole = Role::query()->where('business_id', $business->getKey())->where('slug', Role::MANAGER)->first();

        $this->actingAs($owner)
            ->post("/settings/roles/{$managerRole->getKey()}/clone", ['name' => 'Manager Copy'])
            ->assertSessionHasNoErrors();

        $clone = Role::query()->where('business_id', $business->getKey())->where('name', 'Manager Copy')->first();
        $this->assertNotNull($clone);
        $this->assertFalse($clone->is_system);
        $this->assertSame($managerRole->permissions()->count(), $clone->permissions()->count());
    }

    public function test_owner_can_apply_a_tenant_template_to_a_role(): void
    {
        $this->seed(PlatformRoleSeeder::class);
        [$owner, $business] = $this->createOwnerWithBusiness();
        $role = Role::query()->create(['business_id' => $business->getKey(), 'name' => 'Blank', 'slug' => 'blank', 'is_system' => false]);
        $template = RoleTemplate::factory()->create(['scope' => 'tenant']);
        $template->permissions()->sync(
            Permission::query()->where('scope', Permission::SCOPE_TENANT)->limit(3)->pluck('id'),
        );

        $this->actingAs($owner)
            ->post("/settings/roles/{$role->getKey()}/apply-template", ['role_template_id' => $template->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $role->fresh()->permissions()->count());
    }

    /**
     * This is the exact scope-leak this sprint's fix closes: before the
     * fix, the tenant role form validated `permissions.*` against
     * `exists:permissions,id` with no scope filter, so a platform-only
     * permission (SuperAdmin-side capability) could be attached to a
     * Business Owner's custom role.
     */
    public function test_platform_scope_permission_cannot_be_attached_to_a_tenant_role(): void
    {
        $this->seed(PlatformRoleSeeder::class);
        [$owner] = $this->createOwnerWithBusiness();
        $platformPermissionId = Permission::query()->where('slug', 'platform_roles.manage')->value('id');

        $this->actingAs($owner)->post('/settings/roles', [
            'name' => 'Leaky Role',
            'permissions' => [$platformPermissionId],
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_permission_index_excludes_platform_scope_permissions(): void
    {
        $this->seed(PlatformRoleSeeder::class);
        [$owner] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($owner)->get('/settings/roles');

        $permissionsByModule = $response->viewData('page')['props']['permissions'];
        $slugs = collect($permissionsByModule)->flatten(1)->pluck('slug');

        $this->assertFalse($slugs->contains('platform_roles.manage'));
        $this->assertTrue($slugs->contains('products.view'));
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function createOwnerWithBusiness(): array
    {
        $plan = SubscriptionPlan::factory()->create();

        $owner = app(BusinessRegistrationService::class)->register([
            'owner_name' => 'Owner '.fake()->unique()->numerify('###'),
            'owner_email' => fake()->unique()->safeEmail(),
            'owner_phone' => null,
            'password' => 'Password123!',
            'business_name' => fake()->unique()->company(),
            'business_type' => 'retail',
            'business_phone' => null,
            'country' => 'KE',
            'currency' => 'KES',
            'subscription_plan_id' => $plan->id,
        ]);

        $owner->forceFill(['email_verified_at' => now()])->save();

        return [$owner, $owner->business];
    }
}
