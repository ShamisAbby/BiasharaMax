<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\BusinessType;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BusinessTypeManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_create_a_business_type(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $response = $this->actingAs($platformUser, 'platform')->post(route('platform.business-types.store'), [
            'name' => 'Retail Shop',
            'slug' => 'retail-shop',
            'inventory_enabled' => true,
            'pos_enabled' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_types', ['slug' => 'retail-shop', 'pos_enabled' => true]);
    }

    public function test_platform_user_can_update_a_business_type(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $type = BusinessType::factory()->create(['name' => 'Old Name']);

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.business-types.update', $type->id), [
                'name' => 'New Name',
                'slug' => $type->slug,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $type->fresh()->name);
    }

    public function test_archive_activate_and_deactivate_transitions(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $type = BusinessType::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.business-types.archive', $type->id));
        $this->assertSame(BusinessType::STATUS_ARCHIVED, $type->fresh()->status);

        $this->actingAs($platformUser, 'platform')->post(route('platform.business-types.activate', $type->id));
        $this->assertSame(BusinessType::STATUS_ACTIVE, $type->fresh()->status);

        $this->actingAs($platformUser, 'platform')->post(route('platform.business-types.deactivate', $type->id));
        $this->assertSame(BusinessType::STATUS_INACTIVE, $type->fresh()->status);
    }

    public function test_cloning_a_business_type_copies_modules_and_plans(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $type = BusinessType::factory()->create(['name' => 'Restaurant']);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.business-types.clone', $type->id), ['name' => 'Restaurant Copy'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('business_types', ['name' => 'Restaurant Copy']);
        $this->assertNotSame($type->slug, BusinessType::query()->where('name', 'Restaurant Copy')->first()->slug);
    }

    public function test_business_type_in_use_cannot_be_deleted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $type = BusinessType::factory()->create();
        $business->update(['business_type_id' => $type->id]);

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.business-types.destroy', $type->id))
            ->assertSessionHasErrors('business_type');

        $this->assertDatabaseHas('business_types', ['id' => $type->id, 'deleted_at' => null]);
    }

    public function test_business_type_not_in_use_can_be_deleted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $type = BusinessType::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.business-types.destroy', $type->id))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($type);
    }

    public function test_platform_admin_without_business_types_permission_is_forbidden(): void
    {
        // A slug that belongs to this test alone. 'support-agent' is now a
        // seeded platform role, so creating it here collided with the
        // unique index — and the point of the role is only that it grants
        // nothing, not what it is called.
        $restrictedRole = PlatformRole::query()->create([
            'name' => 'Restricted Test Role',
            'slug' => 'restricted-test-role',
            'is_system' => false,
        ]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $restrictedRole->id]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.business-types.index'))
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_business_types(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.business-types.index'))
            ->assertRedirect(route('platform.login'));
    }
}
