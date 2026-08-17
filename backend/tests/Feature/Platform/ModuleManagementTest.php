<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\BusinessType;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\ModuleManagement\Services\BusinessModuleResolver;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_create_a_module(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.modules.store'), [
            'name' => 'Point of Sale',
            'slug' => 'pos',
            'version' => '1.0.0',
            'visibility' => 'public',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('modules', ['slug' => 'pos']);
    }

    public function test_module_dependencies_can_be_set_on_create(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $core = Module::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.modules.store'), [
            'name' => 'Advanced Reports',
            'slug' => 'advanced-reports',
            'version' => '1.0.0',
            'visibility' => 'public',
            'dependency_ids' => [$core->id],
        ])->assertSessionHasNoErrors();

        $module = Module::query()->where('slug', 'advanced-reports')->first();
        $this->assertSame([$core->id], $module->dependencies()->pluck('modules.id')->all());
    }

    public function test_enable_and_disable_toggle_module_status(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $module = Module::factory()->create(['status' => Module::STATUS_ACTIVE]);

        $this->actingAs($platformUser, 'platform')->post(route('platform.modules.disable', $module->id));
        $this->assertSame(Module::STATUS_INACTIVE, $module->fresh()->status);

        $this->actingAs($platformUser, 'platform')->post(route('platform.modules.enable', $module->id));
        $this->assertSame(Module::STATUS_ACTIVE, $module->fresh()->status);
    }

    public function test_updating_version_records_history(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $module = Module::factory()->create(['version' => '1.0.0']);

        $this->actingAs($platformUser, 'platform')->post(route('platform.modules.version.update', $module->id), [
            'version' => '1.1.0',
            'notes' => 'Bug fixes',
        ])->assertSessionHasNoErrors();

        $this->assertSame('1.1.0', $module->fresh()->version);
        $this->assertDatabaseHas('module_version_history', [
            'module_id' => $module->id,
            'from_version' => '1.0.0',
            'to_version' => '1.1.0',
        ]);
    }

    public function test_module_can_be_assigned_to_plans_and_business_types(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $module = Module::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $type = BusinessType::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.modules.plans.update', $module->id), ['plan_ids' => [$plan->id]])
            ->assertSessionHasNoErrors();

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.modules.business-types.update', $module->id), ['business_type_ids' => [$type->id]])
            ->assertSessionHasNoErrors();

        $this->assertTrue($module->subscriptionPlans()->where('subscription_plans.id', $plan->id)->exists());
        $this->assertTrue($module->businessTypes()->where('business_types.id', $type->id)->exists());
    }

    public function test_module_installed_on_a_business_cannot_be_deleted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $module = Module::factory()->create();
        app(BusinessModuleResolver::class)->install($business, $module);

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.modules.destroy', $module->id))
            ->assertSessionHasErrors('module');

        $this->assertNotNull(Module::find($module->id));
    }

    public function test_tenant_user_cannot_access_module_management(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.modules.index'))
            ->assertRedirect(route('platform.login'));
    }
}
