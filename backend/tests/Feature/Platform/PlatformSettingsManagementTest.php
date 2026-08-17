<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Localization\Models\Currency;
use App\Domain\Localization\Models\TaxRate;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\PlatformRole;
use App\Domain\Settings\Services\PlatformSettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PlatformSettingsManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_settings_index(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.settings.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/System/Settings/Index')
                ->has('settings.general')
                ->has('settings.payment')
            );
    }

    public function test_platform_user_can_update_a_settings_group(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.system.settings.update', 'general'), [
                'platform_name' => 'Updated Platform Name',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('platform_settings', ['key' => 'general.platform_name']);
        $settings = app(PlatformSettingsService::class)->allGrouped();
        $this->assertSame('Updated Platform Name', $settings['general']['platform_name']);
    }

    public function test_updating_an_unknown_settings_group_returns_404(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.system.settings.update', 'not-a-real-group'), ['foo' => 'bar'])
            ->assertNotFound();
    }

    public function test_platform_user_can_create_a_currency(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.settings.currencies.store'), [
                'code' => 'KES',
                'name' => 'Kenyan Shilling',
                'symbol' => 'KSh',
                'exchange_rate_to_base' => 1.5,
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('currencies', ['code' => 'KES']);
    }

    public function test_creating_a_currency_validates_required_fields(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.settings.currencies.store'), [])
            ->assertSessionHasErrors(['code', 'name', 'symbol', 'exchange_rate_to_base']);
    }

    public function test_platform_user_can_update_a_currency(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $currency = Currency::factory()->create(['exchange_rate_to_base' => 1]);

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.system.settings.currencies.update', $currency->id), [
                'exchange_rate_to_base' => 2.5,
                'is_active' => false,
            ])->assertSessionHasNoErrors();

        $currency->refresh();
        $this->assertEquals(2.5, $currency->exchange_rate_to_base);
        $this->assertFalse($currency->is_active);
    }

    public function test_platform_user_can_create_a_tax_rate(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.settings.tax-rates.store'), [
                'name' => 'VAT',
                'country_code' => 'TZ',
                'rate' => 18,
                'is_default' => true,
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tax_rates', ['name' => 'VAT']);
    }

    public function test_platform_user_can_update_a_tax_rate(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $taxRate = TaxRate::factory()->create(['rate' => 10]);

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.system.settings.tax-rates.update', $taxRate->id), [
                'name' => $taxRate->name,
                'rate' => 20,
                'is_default' => false,
                'is_active' => true,
            ])->assertSessionHasNoErrors();

        $this->assertEquals(20, $taxRate->fresh()->rate);
    }

    public function test_platform_user_can_destroy_a_tax_rate(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $taxRate = TaxRate::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.system.settings.tax-rates.destroy', $taxRate->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('tax_rates', ['id' => $taxRate->id]);
    }

    public function test_platform_admin_without_manage_permission_cannot_update_settings(): void
    {
        $role = PlatformRole::query()->create(['name' => 'Viewer', 'slug' => 'settings-viewer', 'is_system' => false]);
        $role->permissions()->sync(
            Permission::query()->where('slug', 'platform_settings.view')->pluck('id'),
        );
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.system.settings.update', 'general'), ['platform_name' => 'X'])
            ->assertForbidden();
    }

    public function test_platform_user_without_view_permission_cannot_view_settings_index(): void
    {
        $role = PlatformRole::query()->create(['name' => 'No Settings', 'slug' => 'no-settings', 'is_system' => false]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.settings.index'))
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_platform_settings(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.system.settings.index'))
            ->assertRedirect(route('platform.login'));
    }
}
