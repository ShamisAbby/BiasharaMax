<?php

namespace Tests\Feature\ModuleManagement;

use App\Domain\ModuleManagement\Models\BusinessModule;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\ModuleManagement\Services\BusinessModuleResolver;
use App\Domain\ModuleManagement\Support\DashboardModule;
use Database\Seeders\DashboardModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * The riskiest thing about this feature is not the switch — it's the
 * default.
 *
 * These tables are empty on an existing installation, so a resolver that
 * treats "no configuration" as "nothing allowed" would take every section
 * away from every live tenant the moment it shipped. Most of what follows
 * exists to hold that default in place.
 */
class DashboardModuleGatingTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(DashboardModuleSeeder::class);
    }

    public function test_a_business_with_no_configuration_keeps_every_section(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->assertSame([], app(BusinessModuleResolver::class)->hiddenSlugs($business));

        foreach (DashboardModule::slugs() as $slug) {
            $this->assertTrue(
                app(BusinessModuleResolver::class)->hasModule($business, $slug),
                "Expected {$slug} to be available with no configuration.",
            );
        }
    }

    /**
     * The failure mode this guards is an outage, not a leak.
     *
     * Before the seeder runs there are no registry rows at all. If that
     * were read as "no modules allowed" then deploying this feature would
     * 404 every page for every existing tenant, before anyone had switched
     * anything off.
     */
    public function test_an_unseeded_registry_hides_nothing(): void
    {
        Module::query()->delete();
        app(BusinessModuleResolver::class)->flush();

        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->assertSame([], app(BusinessModuleResolver::class)->hiddenSlugs($business));

        $this->actingAs($owner)->get(route('inventory.products.index'))->assertOk();
        $this->actingAs($owner)->get(route('reports.index'))->assertOk();
    }

    public function test_disabling_a_section_for_one_business_hides_its_routes(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->get(route('inventory.products.index'))->assertOk();

        $this->disable($business, DashboardModule::INVENTORY);

        // 404, not 403: the business doesn't have the section at all, so
        // telling them it exists but is forbidden would be both wrong and
        // the opposite of "hidden completely".
        $this->actingAs($owner)->get(route('inventory.products.index'))->assertNotFound();
    }

    public function test_disabling_one_section_leaves_the_others_alone(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->disable($business, DashboardModule::INVENTORY);

        $this->actingAs($owner)->get(route('sales.orders.index'))->assertOk();
        $this->actingAs($owner)->get(route('reports.index'))->assertOk();
    }

    public function test_a_globally_disabled_module_cannot_be_re_enabled_per_business(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $module = Module::query()->where('slug', DashboardModule::FINANCE)->firstOrFail();
        $module->update(['status' => Module::STATUS_INACTIVE]);

        // An explicit per-business override that says "on"...
        BusinessModule::query()->create([
            'business_id' => $business->getKey(),
            'module_id' => $module->getKey(),
            'is_enabled' => true,
        ]);

        app(BusinessModuleResolver::class)->flush();

        // ...must not beat the platform-wide kill switch, or "disabled
        // everywhere" would only be a suggestion.
        $this->assertFalse(
            app(BusinessModuleResolver::class)->hasModule($business, DashboardModule::FINANCE),
        );

        $this->actingAs($owner)->get(route('finance.journal.index'))->assertNotFound();
    }

    public function test_an_override_can_grant_a_section_the_plan_does_not_include(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $plan = $business->subscription->plan;
        $inventory = Module::query()->where('slug', DashboardModule::INVENTORY)->firstOrFail();

        // A plan that includes only Inventory excludes everything else...
        $plan->modules()->sync([$inventory->getKey()]);
        app(BusinessModuleResolver::class)->flush();

        $this->assertFalse(
            app(BusinessModuleResolver::class)->hasModule($business, DashboardModule::FINANCE),
        );

        // ...until support grants an exception for this one customer.
        $finance = Module::query()->where('slug', DashboardModule::FINANCE)->firstOrFail();
        app(BusinessModuleResolver::class)->install($business, $finance);

        $this->assertTrue(
            app(BusinessModuleResolver::class)->hasModule($business, DashboardModule::FINANCE),
        );
    }

    public function test_the_shared_module_list_matches_what_the_routes_allow(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->disable($business, DashboardModule::WEBSITE);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $hidden = $response->viewData('page')['props']['auth']['hiddenModules'];

        // The sidebar hides what the routes refuse. If these two ever
        // disagree the user gets either a dead link or an invisible page.
        $this->assertContains(DashboardModule::WEBSITE, $hidden);
        $this->assertNotContains(DashboardModule::SALES, $hidden);
    }

    /**
     * Regression guard for a crash the module resolver caused.
     *
     * `businessType` serialises to `business_type`, the same key as the
     * string column. The resolver used to reach the pivot through
     * `$business->businessType->modules`, which loaded the relation onto
     * the very Business instance shared as a page prop — turning
     * `business_type` from a string into an object and making every page
     * that renders it throw React error #31, "Objects are not valid as a
     * React child".
     *
     * Asserting the type here is what makes that specific mistake loud.
     */
    public function test_the_resolver_does_not_turn_business_type_into_an_object(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $props = $this->actingAs($owner)->get(route('dashboard'))->viewData('page')['props'];

        $this->assertIsString($props['auth']['business']['business_type']);
        $this->assertIsString($props['business']['business_type']);
    }

    public function test_search_does_not_return_results_from_a_disabled_section(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        \App\Domain\Sales\Models\Customer::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'Findable Customer',
        ]);

        $groups = $this->actingAs($owner)
            ->getJson(route('search', ['q' => 'Findable']))
            ->json('groups');

        $this->assertContains('Customers', array_column($groups, 'group'));

        // Customers are served by the Sales module, so switching Sales off
        // must take them out of search too — otherwise search links to a
        // page that now 404s.
        $this->disable($business, DashboardModule::SALES);

        $groups = $this->actingAs($owner)
            ->getJson(route('search', ['q' => 'Findable']))
            ->json('groups');

        $this->assertNotContains('Customers', array_column($groups, 'group'));
    }

    public function test_a_super_admin_can_toggle_and_then_clear_an_override(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $platformUser = \App\Domain\Authentication\Models\PlatformUser::factory()->create();

        $module = Module::query()->where('slug', DashboardModule::REPORTS)->firstOrFail();

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.businesses.modules.update', $business->getKey()), [
                'module_id' => $module->getKey(),
                'enabled' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('business_module', [
            'business_id' => $business->getKey(),
            'module_id' => $module->getKey(),
            'is_enabled' => false,
        ]);

        // Clearing removes the exception rather than forcing the opposite.
        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.businesses.modules.update', $business->getKey()), [
                'module_id' => $module->getKey(),
                'enabled' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('business_module', [
            'business_id' => $business->getKey(),
            'module_id' => $module->getKey(),
        ]);
    }

    public function test_re_seeding_does_not_switch_a_disabled_module_back_on(): void
    {
        $module = Module::query()->where('slug', DashboardModule::EMPLOYEES)->firstOrFail();
        $module->update(['status' => Module::STATUS_INACTIVE]);

        $this->seed(DashboardModuleSeeder::class);

        $this->assertSame(
            Module::STATUS_INACTIVE,
            $module->fresh()->status,
            'Re-seeding must not undo an operator decision.',
        );
    }

    // ---------------------------------------------------------------

    private function disable(\App\Domain\Business\Models\Business $business, string $slug): void
    {
        $module = Module::query()->where('slug', $slug)->firstOrFail();

        app(BusinessModuleResolver::class)->uninstall($business, $module);
        app(BusinessModuleResolver::class)->flush();
    }
}
