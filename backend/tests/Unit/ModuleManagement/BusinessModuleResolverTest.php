<?php

namespace Tests\Unit\ModuleManagement;

use App\Domain\Business\Models\BusinessType;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\ModuleManagement\Services\BusinessModuleResolver;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BusinessModuleResolverTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private BusinessModuleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(BusinessModuleResolver::class);
    }

    public function test_business_with_no_business_type_has_no_default_modules(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $this->assertTrue($this->resolver->effectiveModules($business)->isEmpty());
    }

    public function test_effective_modules_default_to_the_business_types_modules(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $type = BusinessType::factory()->create();
        $inventory = Module::factory()->create(['name' => 'Inventory']);
        $pos = Module::factory()->create(['name' => 'POS']);
        $type->modules()->sync([$inventory->id, $pos->id]);
        $business->update(['business_type_id' => $type->id]);

        $effective = $this->resolver->effectiveModules($business->fresh());

        $this->assertCount(2, $effective);
        $this->assertTrue($this->resolver->hasModule($business->fresh(), $inventory->slug));
    }

    public function test_plan_restricts_business_type_defaults_to_their_intersection(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $type = BusinessType::factory()->create();
        $inventory = Module::factory()->create();
        $pos = Module::factory()->create();
        $type->modules()->sync([$inventory->id, $pos->id]);
        $business->update(['business_type_id' => $type->id]);

        $business->subscription->plan->modules()->sync([$inventory->id]);

        $effective = $this->resolver->effectiveModules($business->fresh());

        $this->assertCount(1, $effective);
        $this->assertTrue($this->resolver->hasModule($business->fresh(), $inventory->slug));
        $this->assertFalse($this->resolver->hasModule($business->fresh(), $pos->slug));
    }

    public function test_plan_with_no_modules_assigned_does_not_restrict_defaults(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $type = BusinessType::factory()->create();
        $inventory = Module::factory()->create();
        $type->modules()->sync([$inventory->id]);
        $business->update(['business_type_id' => $type->id]);

        $this->assertTrue($business->subscription->plan->modules()->get()->isEmpty());

        $this->assertTrue($this->resolver->hasModule($business->fresh(), $inventory->slug));
    }

    public function test_per_business_override_can_disable_a_default_module(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $type = BusinessType::factory()->create();
        $inventory = Module::factory()->create();
        $type->modules()->sync([$inventory->id]);
        $business->update(['business_type_id' => $type->id]);

        $this->resolver->uninstall($business->fresh(), $inventory);

        $this->assertFalse($this->resolver->hasModule($business->fresh(), $inventory->slug));
    }

    public function test_per_business_override_can_enable_a_module_excluded_by_the_plan(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $type = BusinessType::factory()->create();
        $inventory = Module::factory()->create();
        $crm = Module::factory()->create();
        $type->modules()->sync([$inventory->id, $crm->id]);
        $business->update(['business_type_id' => $type->id]);

        $business->subscription->plan->modules()->sync([$inventory->id]);
        $this->assertFalse($this->resolver->hasModule($business->fresh(), $crm->slug));

        $this->resolver->install($business->fresh(), $crm);

        $this->assertTrue($this->resolver->hasModule($business->fresh(), $crm->slug));
    }

    public function test_install_is_idempotent_via_update_or_create(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $module = Module::factory()->create();

        $this->resolver->install($business, $module);
        $this->resolver->install($business, $module);

        $this->assertSame(1, \App\Domain\ModuleManagement\Models\BusinessModule::query()
            ->where('business_id', $business->id)->where('module_id', $module->id)->count());
    }
}
