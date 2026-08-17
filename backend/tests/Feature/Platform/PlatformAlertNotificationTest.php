<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Models\PlatformNotificationState;
use App\Domain\Platform\Notifications\PlatformAlertDigestNotification;
use App\Domain\Platform\Notifications\PlatformAlertNotification;
use App\Domain\Security\Models\SecurityAlert;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Emailing and dismissing the platform alert feed.
 *
 * The feed is derived — recomputed from live conditions on every call —
 * which makes both features harder than they look and makes their
 * failure modes specific:
 *
 *  - Without a record of what has been sent, "email each alert
 *    immediately" re-sends every alert every minute, forever. That is
 *    the single worst outcome here, because it trains the recipient to
 *    ignore the mailbox.
 *  - Without stored dismissals, Clear appears to work and everything
 *    returns on the next poll.
 *
 * Both are asserted by running the same operation twice, which is the
 * only way either bug shows up.
 */
class PlatformAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);

        config()->set('platform_notifications.recipients', ['ops@example.com']);
        config()->set('platform_notifications.email_severities', ['critical', 'high']);
    }

    private function criticalAlert(string $description = 'Brute force detected'): SecurityAlert
    {
        return SecurityAlert::query()->create([
            'type' => 'brute_force',
            'severity' => SecurityAlert::SEVERITY_CRITICAL,
            'description' => $description,
            'is_resolved' => false,
        ]);
    }

    public function test_a_new_alert_is_emailed_once_and_not_again(): void
    {
        Notification::fake();

        $this->criticalAlert();

        $this->artisan('platform:dispatch-alerts')->assertSuccessful();
        Notification::assertCount(1);
        Notification::assertSentOnDemand(PlatformAlertNotification::class);

        // The condition is still true, so the feed still contains it.
        // Emailing again would be the bug.
        $this->artisan('platform:dispatch-alerts')->assertSuccessful();
        Notification::assertCount(1);
    }

    public function test_medium_severity_is_not_emailed(): void
    {
        Notification::fake();

        SecurityAlert::query()->create([
            'type' => 'new_device',
            'severity' => SecurityAlert::SEVERITY_LOW,
            'description' => 'First sign-in from a new device.',
            'is_resolved' => false,
        ]);

        $this->artisan('platform:dispatch-alerts')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /**
     * The whole point of pruning state when a key stops appearing.
     *
     * A problem that is fixed and then recurs is genuinely new, and must
     * be emailed again — otherwise the second outage is silent.
     */
    public function test_a_resolved_then_recurring_problem_alerts_again(): void
    {
        Notification::fake();

        $alert = $this->criticalAlert();

        $this->artisan('platform:dispatch-alerts')->assertSuccessful();
        Notification::assertCount(1);

        $alert->update(['is_resolved' => true]);
        $this->artisan('platform:dispatch-alerts')->assertSuccessful();

        // Its memory goes with it, so nothing lingers to suppress a
        // future occurrence.
        $this->assertDatabaseCount('platform_notification_states', 0);

        $this->criticalAlert('Brute force detected again');
        $this->artisan('platform:dispatch-alerts')->assertSuccessful();

        Notification::assertCount(2);
    }

    public function test_no_recipients_marks_alerts_seen_rather_than_queueing_a_backlog(): void
    {
        Notification::fake();
        config()->set('platform_notifications.recipients', []);

        $this->criticalAlert();

        $this->artisan('platform:dispatch-alerts')->assertSuccessful();

        Notification::assertNothingSent();

        // Marked anyway: configuring an address later must not deliver
        // every alert accumulated since install in one burst.
        $this->assertNotNull(
            PlatformNotificationState::query()->first()?->emailed_at,
        );

        config()->set('platform_notifications.recipients', ['ops@example.com']);
        $this->artisan('platform:dispatch-alerts')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_flood_is_summarised_instead_of_sent_individually(): void
    {
        Notification::fake();
        config()->set('platform_notifications.max_individual_emails_per_run', 2);

        // The feed caps security alerts at 5, which is above the
        // threshold set here.
        for ($i = 0; $i < 5; $i++) {
            $this->criticalAlert("Attempt {$i}");
        }

        $this->artisan('platform:dispatch-alerts')->assertSuccessful();

        // One message, and specifically the digest — five individual
        // sends would also be "some notifications", so the count alone
        // does not say the circuit breaker tripped.
        Notification::assertCount(1);
        Notification::assertSentOnDemand(PlatformAlertDigestNotification::class);

        // All five are still recorded, so none of them is emailed again
        // on the next run.
        $this->assertDatabaseCount('platform_notification_states', 5);
    }

    public function test_dismissing_hides_an_item_across_polls(): void
    {
        $admin = PlatformUser::factory()->create();
        $alert = $this->criticalAlert();

        $key = 'security-alert-'.$alert->id;

        $this->actingAs($admin, 'platform')
            ->postJson(route('platform.notifications.dismiss'), ['key' => $key])
            ->assertOk();

        $feed = $this->actingAs($admin, 'platform')
            ->getJson(route('platform.notifications.live'))
            ->json('items');

        $this->assertNotContains($key, collect($feed)->pluck('id')->all());

        // Again, because a derived feed regenerating the item is exactly
        // the failure this guards.
        $feed = $this->actingAs($admin, 'platform')
            ->getJson(route('platform.notifications.live'))
            ->json('items');

        $this->assertNotContains($key, collect($feed)->pluck('id')->all());
    }

    /**
     * Dismissal is an acknowledgement, not a mute.
     *
     * Clearing the top bar must never stop the on-call email for an
     * unresolved critical alert — which is why the dispatcher reads the
     * unfiltered feed.
     */
    public function test_dismissing_does_not_suppress_the_email(): void
    {
        Notification::fake();

        $admin = PlatformUser::factory()->create();
        $alert = $this->criticalAlert();

        $this->actingAs($admin, 'platform')
            ->postJson(route('platform.notifications.dismiss'), [
                'key' => 'security-alert-'.$alert->id,
            ])
            ->assertOk();

        $this->artisan('platform:dispatch-alerts')->assertSuccessful();

        Notification::assertCount(1);
    }

    public function test_an_unknown_key_cannot_be_dismissed(): void
    {
        $admin = PlatformUser::factory()->create();

        $this->actingAs($admin, 'platform')
            ->postJson(route('platform.notifications.dismiss'), [
                'key' => 'security-alert-not-a-real-thing',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('platform_notification_states', 0);
    }

    public function test_clear_all_dismisses_everything_visible(): void
    {
        $admin = PlatformUser::factory()->create();

        $this->criticalAlert('One');
        $this->criticalAlert('Two');

        $this->actingAs($admin, 'platform')
            ->postJson(route('platform.notifications.dismiss-all'))
            ->assertOk()
            ->assertJson(['dismissed' => 2]);

        $feed = $this->actingAs($admin, 'platform')
            ->getJson(route('platform.notifications.live'))
            ->json('items');

        $this->assertSame([], $feed);
    }

    public function test_a_guest_cannot_dismiss(): void
    {
        $this->postJson(route('platform.notifications.dismiss'), ['key' => 'anything'])
            ->assertUnauthorized();
    }
}
