<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Models\NotificationChannel;
use App\Domain\Notifications\Models\NotificationDelivery;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * The Notification Centre, which on a fresh installation could not send
 * anything and did not say why.
 *
 * The chain was: no channels are seeded → the page had no way to create
 * one → every campaign found no enabled channel → each wrote a failed
 * delivery per recipient → the row showed a bare red "failed". Four
 * separate places where the system knew what was wrong and none of them
 * said so.
 */
class NotificationCenterTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    private function campaign(string $channel = NotificationChannel::CHANNEL_IN_APP): NotificationCampaign
    {
        return NotificationCampaign::query()->create([
            'name' => 'Testing',
            'channel' => $channel,
            'subject' => 'Scheduled maintenance',
            'body' => 'We will be offline briefly on Sunday.',
            'audience_type' => NotificationCampaign::AUDIENCE_ALL_BUSINESSES,
            'status' => NotificationCampaign::STATUS_DRAFT,
        ]);
    }

    private function enabledInAppChannel(): NotificationChannel
    {
        return NotificationChannel::query()->create([
            'name' => 'In-app',
            'channel' => NotificationChannel::CHANNEL_IN_APP,
            'provider' => 'database',
            'is_enabled' => true,
        ]);
    }

    /**
     * The behaviour that replaces a wall of identical failures.
     *
     * A campaign with no enabled channel cannot succeed for anyone, so it
     * is refused before a single delivery row is written.
     */
    public function test_sending_without_an_enabled_channel_is_refused_with_a_reason(): void
    {
        $admin = PlatformUser::factory()->create();
        $this->createOwnerWithBusiness();
        $campaign = $this->campaign();

        $this->actingAs($admin, 'platform')
            ->from(route('platform.operations.notifications.index'))
            ->post(route('platform.operations.notifications.campaigns.send', $campaign->id))
            ->assertSessionHasErrors('campaign');

        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->assertSame(NotificationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
    }

    public function test_a_disabled_channel_does_not_count_as_available(): void
    {
        $admin = PlatformUser::factory()->create();
        $this->createOwnerWithBusiness();

        NotificationChannel::query()->create([
            'name' => 'In-app',
            'channel' => NotificationChannel::CHANNEL_IN_APP,
            'provider' => 'database',
            'is_enabled' => false,
        ]);

        $this->actingAs($admin, 'platform')
            ->from(route('platform.operations.notifications.index'))
            ->post(route('platform.operations.notifications.campaigns.send', $this->campaign()->id))
            ->assertSessionHasErrors('campaign');
    }

    public function test_a_campaign_with_no_recipients_is_refused(): void
    {
        $admin = PlatformUser::factory()->create();
        $this->enabledInAppChannel();

        // No businesses at all, so nobody to send to.
        $this->actingAs($admin, 'platform')
            ->from(route('platform.operations.notifications.index'))
            ->post(route('platform.operations.notifications.campaigns.send', $this->campaign()->id))
            ->assertSessionHasErrors('campaign');
    }

    /**
     * End to end on the one channel that needs no external provider.
     *
     * In-app previously fell through to the generic HTTP driver and tried
     * to POST to a webhook URL it does not have — so the channel an
     * administrator could actually turn on was the one that could not
     * deliver.
     */
    public function test_an_in_app_campaign_reaches_the_business_owners_bell(): void
    {
        $admin = PlatformUser::factory()->create();
        [$owner] = $this->createOwnerWithBusiness();
        $this->enabledInAppChannel();

        $campaign = $this->campaign();

        $this->actingAs($admin, 'platform')
            ->post(route('platform.operations.notifications.campaigns.send', $campaign->id))
            ->assertSessionHasNoErrors();

        $campaign->refresh();

        $this->assertSame(NotificationCampaign::STATUS_SENT, $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_campaign_id' => $campaign->id,
            'status' => NotificationDelivery::STATUS_SENT,
        ]);

        // The point of the whole exercise: it is in their bell.
        $this->assertSame(1, $owner->fresh()->notifications()->count());
    }

    public function test_an_admin_can_create_a_channel_and_it_starts_disabled(): void
    {
        $admin = PlatformUser::factory()->create();

        $this->actingAs($admin, 'platform')
            ->post(route('platform.operations.notifications.channels.store'), [
                'name' => 'In-app notifications',
                'channel' => NotificationChannel::CHANNEL_IN_APP,
                'provider' => 'database',
            ])
            ->assertSessionHasNoErrors();

        $channel = NotificationChannel::query()->firstOrFail();

        // Disabled until someone deliberately enables it — a channel
        // created live and enabled could be picked by a campaign before
        // its credentials are filled in.
        $this->assertFalse($channel->is_enabled);
    }

    public function test_the_index_reports_which_channel_types_can_deliver(): void
    {
        $admin = PlatformUser::factory()->create();
        $this->enabledInAppChannel();

        $this->actingAs($admin, 'platform')
            ->get(route('platform.operations.notifications.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Operations/Notifications/Index')
                ->where('enabledChannels', [NotificationChannel::CHANNEL_IN_APP])
                ->has('channelTypes')
            );
    }

    /**
     * Creating and sending are separate permissions upstream, and the
     * send guard must not quietly become the only check.
     */
    public function test_sending_requires_the_send_permission(): void
    {
        $role = \App\Domain\RBAC\Models\PlatformRole::query()->create([
            'name' => 'Notifications Viewer',
            'slug' => 'notifications-viewer',
            'is_system' => false,
        ]);
        $role->permissions()->attach(
            \App\Domain\RBAC\Models\Permission::query()
                ->where('slug', 'platform_notifications.view')
                ->firstOrFail()->id,
        );

        $viewer = PlatformUser::factory()->create(['platform_role_id' => $role->id]);
        $this->createOwnerWithBusiness();
        $this->enabledInAppChannel();

        $this->actingAs($viewer, 'platform')
            ->post(route('platform.operations.notifications.campaigns.send', $this->campaign()->id))
            ->assertForbidden();

        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    /**
     * The report that started this: email showed "Not configured" no
     * matter what was in .env.
     *
     * Email has no credentials of its own — it uses the application's
     * mail transport — so the old `filled($this->credentials)` rule could
     * never be satisfied for it.
     */
    public function test_email_is_configured_from_the_application_mailer(): void
    {
        $channel = NotificationChannel::query()->create([
            'name' => 'Email (SMTP)',
            'channel' => NotificationChannel::CHANNEL_EMAIL,
            'provider' => 'smtp',
            'is_enabled' => false,
        ]);

        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', ['transport' => 'smtp', 'host' => 'smtp.hostinger.com']);
        config()->set('mail.from.address', 'hello@example.com');

        $this->assertTrue($channel->isConfigured());
        $this->assertNull($channel->configurationHint());
    }

    public function test_a_log_mailer_does_not_count_as_able_to_deliver(): void
    {
        $channel = NotificationChannel::query()->create([
            'name' => 'Email (SMTP)',
            'channel' => NotificationChannel::CHANNEL_EMAIL,
            'provider' => 'smtp',
        ]);

        config()->set('mail.default', 'log');
        config()->set('mail.mailers.log', ['transport' => 'log']);

        // A correct local setup, but nothing reaches an inbox — saying
        // "configured" here would claim campaign email works.
        $this->assertFalse($channel->isConfigured());
        $this->assertStringContainsString('MAIL_MAILER', (string) $channel->configurationHint());
    }

    /**
     * Configured and enabled are different questions.
     *
     * The old rule required `is_enabled`, which made the documented
     * workflow — configure, then enable — impossible to reach: nothing
     * could report as configured until it was already switched on.
     */
    public function test_a_disabled_channel_can_still_be_configured(): void
    {
        $channel = NotificationChannel::query()->create([
            'name' => 'In-app',
            'channel' => NotificationChannel::CHANNEL_IN_APP,
            'provider' => 'database',
            'is_enabled' => false,
        ]);

        $this->assertTrue($channel->isConfigured());
        $this->assertFalse($channel->is_enabled);
    }

    public function test_a_third_party_channel_still_needs_credentials(): void
    {
        $channel = NotificationChannel::query()->create([
            'name' => 'SMS',
            'channel' => NotificationChannel::CHANNEL_SMS,
            'provider' => 'africastalking',
        ]);

        $this->assertFalse($channel->isConfigured());

        $channel->update(['credentials' => ['api_key' => 'secret']]);

        $this->assertTrue($channel->fresh()->isConfigured());
    }
}
