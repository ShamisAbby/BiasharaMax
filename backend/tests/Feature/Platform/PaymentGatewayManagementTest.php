<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PaymentGatewayManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_gateways_index(): void
    {
        $platformUser = PlatformUser::factory()->create();
        PaymentGateway::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.gateways.index'))
            ->assertOk();
    }

    public function test_platform_user_can_create_a_gateway(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.finance.gateways.store'), [
            'name' => 'My Stripe',
            'slug' => 'my-stripe',
            'provider' => PaymentGateway::PROVIDER_STRIPE,
            'mode' => PaymentGateway::MODE_SANDBOX,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payment_gateways', ['slug' => 'my-stripe']);
    }

    public function test_updating_with_blank_credentials_does_not_wipe_existing_secrets(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $gateway = PaymentGateway::factory()->create(['credentials' => ['secret_key' => 'sk_test_existing']]);

        $this->actingAs($platformUser, 'platform')->patch(route('platform.finance.gateways.update', $gateway->id), [
            'name' => $gateway->name,
            'slug' => $gateway->slug,
            'provider' => $gateway->provider,
            'mode' => 'production',
            'credentials' => ['secret_key' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame('sk_test_existing', $gateway->fresh()->credentials['secret_key']);
        $this->assertSame('production', $gateway->fresh()->mode);
    }

    public function test_enabling_and_disabling_a_gateway(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $gateway = PaymentGateway::factory()->create(['is_enabled' => false]);

        $this->actingAs($platformUser, 'platform')->post(route('platform.finance.gateways.enable', $gateway->id));
        $this->assertTrue($gateway->fresh()->is_enabled);

        $this->actingAs($platformUser, 'platform')->post(route('platform.finance.gateways.disable', $gateway->id));
        $this->assertFalse($gateway->fresh()->is_enabled);
    }

    public function test_platform_user_can_view_gateway_logs(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $gateway = PaymentGateway::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.gateways.logs', $gateway->id))
            ->assertOk();
    }

    public function test_platform_admin_without_gateway_management_permission_cannot_create(): void
    {
        $role = PlatformRole::query()->create(['name' => 'Viewer', 'slug' => 'gateway-viewer', 'is_system' => false]);
        $role->permissions()->sync(
            \App\Domain\RBAC\Models\Permission::query()->where('slug', 'payment_gateways.view')->pluck('id'),
        );
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')->post(route('platform.finance.gateways.store'), [
            'name' => 'X', 'slug' => 'x', 'provider' => 'custom', 'mode' => 'sandbox',
        ])->assertForbidden();
    }

    public function test_tenant_user_cannot_access_gateways(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.finance.gateways.index'))
            ->assertRedirect(route('platform.login'));
    }
}
