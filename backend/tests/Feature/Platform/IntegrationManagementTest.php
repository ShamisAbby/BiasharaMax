<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Integrations\Models\Integration;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class IntegrationManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_integrations_index(): void
    {
        $platformUser = PlatformUser::factory()->create();
        Integration::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.integrations.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/System/Integrations/Index')
                ->has('integrations', 1)
            );
    }

    public function test_platform_user_can_create_an_integration(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.system.integrations.store'), [
            'name' => 'My Slack',
            'slug' => 'my-slack',
            'category' => Integration::CATEGORY_COMMUNICATION,
            'provider' => 'slack',
            'mode' => Integration::MODE_SANDBOX,
            'credentials' => ['bot_token' => 'xoxb-secret'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('integrations', ['slug' => 'my-slack']);
        $integration = Integration::query()->where('slug', 'my-slack')->first();
        $this->assertSame('xoxb-secret', $integration->credentials['bot_token']);
    }

    public function test_updating_with_blank_credentials_does_not_wipe_existing_secrets(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $integration = Integration::factory()->create(['credentials' => ['api_key' => 'sk-existing']]);

        $this->actingAs($platformUser, 'platform')->patch(route('platform.system.integrations.update', $integration->id), [
            'name' => $integration->name,
            'slug' => $integration->slug,
            'category' => $integration->category,
            'provider' => $integration->provider,
            'mode' => 'production',
            'credentials' => ['api_key' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame('sk-existing', $integration->fresh()->credentials['api_key']);
        $this->assertSame('production', $integration->fresh()->mode);
    }

    public function test_updating_with_a_non_blank_credential_overwrites_and_merges(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $integration = Integration::factory()->create(['credentials' => ['api_key' => 'sk-old', 'team_id' => 'T123']]);

        $this->actingAs($platformUser, 'platform')->patch(route('platform.system.integrations.update', $integration->id), [
            'name' => $integration->name,
            'slug' => $integration->slug,
            'category' => $integration->category,
            'provider' => $integration->provider,
            'mode' => $integration->mode,
            'credentials' => ['api_key' => 'sk-new'],
        ])->assertSessionHasNoErrors();

        $integration->refresh();
        $this->assertSame('sk-new', $integration->credentials['api_key']);
        $this->assertSame('T123', $integration->credentials['team_id']);
    }

    public function test_enabling_and_disabling_an_integration(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $integration = Integration::factory()->create(['is_enabled' => false]);

        $this->actingAs($platformUser, 'platform')->post(route('platform.system.integrations.enable', $integration->id));
        $this->assertTrue($integration->fresh()->is_enabled);

        $this->actingAs($platformUser, 'platform')->post(route('platform.system.integrations.disable', $integration->id));
        $this->assertFalse($integration->fresh()->is_enabled);
    }

    public function test_platform_user_can_trigger_a_connection_test(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response(['ok' => true, 'team' => 'Acme', 'user' => 'bot'], 200),
        ]);

        $platformUser = PlatformUser::factory()->create();
        $integration = Integration::factory()->create([
            'provider' => 'slack',
            'category' => Integration::CATEGORY_COMMUNICATION,
            'is_enabled' => true,
            'credentials' => ['bot_token' => 'xoxb-test'],
        ]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.integrations.test', $integration->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('integration_logs', ['integration_id' => $integration->id, 'is_successful' => true]);
        $this->assertSame('success', $integration->fresh()->last_test_result);
    }

    public function test_platform_user_can_view_integration_logs(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $integration = Integration::factory()->create();
        $integration->logs()->create([
            'direction' => 'outbound',
            'event_type' => 'connection_test',
            'is_successful' => true,
        ]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.integrations.logs', $integration->id))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/System/Integrations/Logs')
                ->has('logs.data', 1)
            );
    }

    public function test_platform_admin_without_manage_permission_cannot_create_an_integration(): void
    {
        $role = PlatformRole::query()->create(['name' => 'Viewer', 'slug' => 'integrations-viewer', 'is_system' => false]);
        $role->permissions()->sync(
            Permission::query()->where('slug', 'integrations.view')->pluck('id'),
        );
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')->post(route('platform.system.integrations.store'), [
            'name' => 'X', 'slug' => 'x', 'category' => Integration::CATEGORY_CUSTOM, 'provider' => 'custom', 'mode' => 'sandbox',
        ])->assertForbidden();
    }

    public function test_platform_user_without_view_permission_cannot_view_integrations(): void
    {
        $role = PlatformRole::query()->create(['name' => 'No Integrations', 'slug' => 'no-integrations', 'is_system' => false]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.integrations.index'))
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_integrations(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.system.integrations.index'))
            ->assertRedirect(route('platform.login'));
    }
}
