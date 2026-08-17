<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Models\NotificationChannel;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_create_and_configure_a_channel(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.notifications.channels.store'), [
            'name' => 'My Email',
            'channel' => 'email',
            'provider' => 'smtp',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notification_channels', ['name' => 'My Email']);
    }

    public function test_updating_channel_with_blank_credentials_does_not_wipe_existing_secrets(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $channel = NotificationChannel::factory()->create(['credentials' => ['api_key' => 'secret-value']]);

        $this->actingAs($platformUser, 'platform')->patch(route('platform.operations.notifications.channels.update', $channel->id), [
            'name' => $channel->name,
            'channel' => $channel->channel,
            'provider' => $channel->provider,
            'credentials' => ['api_key' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame('secret-value', $channel->fresh()->credentials['api_key']);
    }

    public function test_sending_a_campaign_with_no_enabled_channel_marks_deliveries_failed(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $campaign = NotificationCampaign::factory()->create(['channel' => 'email', 'audience_type' => 'all_businesses']);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.notifications.campaigns.send', $campaign->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(NotificationCampaign::STATUS_FAILED, $campaign->fresh()->status);
        $this->assertSame(0, $campaign->fresh()->sent_count);
    }

    public function test_sending_a_campaign_with_an_enabled_channel_succeeds(): void
    {
        Mail::fake();
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        NotificationChannel::factory()->create(['channel' => 'email', 'is_enabled' => true]);
        $campaign = NotificationCampaign::factory()->create(['channel' => 'email', 'audience_type' => 'all_businesses']);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.notifications.campaigns.send', $campaign->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(NotificationCampaign::STATUS_SENT, $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->sent_count);
    }

    public function test_platform_admin_without_send_permission_cannot_send_a_campaign(): void
    {
        $role = \App\Domain\RBAC\Models\PlatformRole::query()->create(['name' => 'Viewer', 'slug' => 'notif-viewer', 'is_system' => false]);
        $role->permissions()->sync(
            \App\Domain\RBAC\Models\Permission::query()->where('slug', 'platform_notifications.view')->pluck('id'),
        );
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);
        $campaign = NotificationCampaign::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.notifications.campaigns.send', $campaign->id))
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_notifications(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.operations.notifications.index'))
            ->assertRedirect(route('platform.login'));
    }
}
