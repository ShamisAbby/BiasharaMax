<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Developer\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class MonitoringAndDeveloperCenterTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_the_monitoring_dashboard_with_real_metrics(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.operations.monitoring.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Operations/Monitoring/Index')
                ->has('live.platform_version')
            );
    }

    public function test_monitoring_snapshot_command_records_a_real_snapshot(): void
    {
        $this->artisan('monitoring:snapshot')->assertExitCode(0);

        $this->assertDatabaseCount('system_health_snapshots', 1);
    }

    public function test_platform_user_can_view_developer_center_with_real_system_info(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.operations.developer.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Operations/Developer/Index')
                ->where('systemInfo.laravel_version', fn ($v) => $v === app()->version())
            );
    }

    public function test_platform_user_can_create_and_dispatch_a_webhook(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.developer.webhooks.store'), [
            'name' => 'Test Webhook',
            'url' => 'https://example.com/hook',
            'events' => ['business.created'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('webhooks', ['name' => 'Test Webhook']);
    }

    public function test_failed_webhook_delivery_can_be_retried(): void
    {
        Http::fakeSequence()->push(['ok' => false], 500)->push(['ok' => true], 200);
        $platformUser = PlatformUser::factory()->create();
        $webhook = Webhook::factory()->create();
        $delivery = app(\App\Domain\Developer\Services\WebhookDispatchService::class)->dispatch($webhook, 'test.event', ['foo' => 'bar']);

        $this->assertFalse($delivery->is_successful);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.developer.webhooks.deliveries.retry', $delivery->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $webhook->deliveries()->count());
    }

    public function test_platform_user_can_set_up_and_confirm_two_factor_authentication(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.profile.two-factor.setup'))
            ->assertSessionHasNoErrors();

        $credential = \App\Domain\Security\Models\TwoFactorCredential::query()
            ->where('authenticatable_type', 'platform_user')
            ->where('authenticatable_id', $platformUser->id)
            ->first();
        $this->assertNotNull($credential);
        $this->assertFalse($credential->isEnabled());

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $validOtp = $google2fa->getCurrentOtp($credential->secret);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.profile.two-factor.confirm'), ['code' => $validOtp])
            ->assertSessionHasNoErrors();

        $this->assertTrue($credential->fresh()->isEnabled());
    }

    public function test_tenant_user_cannot_access_monitoring_or_developer_center(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->get(route('platform.operations.monitoring.index'))->assertRedirect(route('platform.login'));
        $this->actingAs($owner)->get(route('platform.operations.developer.index'))->assertRedirect(route('platform.login'));
    }
}
