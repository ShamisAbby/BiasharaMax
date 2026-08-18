<?php

namespace Tests\Feature\Subscription;

use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Models\Subscription;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * A locked account must answer JSON callers in JSON.
 *
 * `notifications.index` sits behind the subscription gate and is polled
 * every 30 seconds by the topbar bell on every screen. The gate used to
 * answer it with a 302 to the subscription page; axios follows redirects,
 * so the poller received HTML, read `data.notifications` as undefined,
 * stored that, and then threw on `.length` — blanking the whole
 * application at the exact moment a subscription expired.
 *
 * The error said "Cannot read properties of undefined (reading 'length')"
 * and named no redirect, no subscription and no poller. It took a byte
 * offset in a minified bundle to find. These tests exist so the shape of
 * the answer is pinned, not the shape of the crash.
 */
class LockedSubscriptionApiResponseTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_a_json_poller_gets_json_not_a_redirect_to_html(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->subscription->forceFill([
            'status' => Subscription::STATUS_SUSPENDED,
        ])->save();

        $this->actingAs($owner)
            ->getJson(route('notifications.index'))
            ->assertStatus(402)
            ->assertJsonStructure(['message', 'reason']);
    }

    public function test_a_suspended_business_is_named_as_such_to_json_callers(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->update(['status' => Business::STATUS_SUSPENDED]);

        $this->actingAs($owner)
            ->getJson(route('notifications.index'))
            ->assertStatus(402)
            ->assertJsonPath('reason', 'business_suspended');
    }

    /**
     * Browsers still get sent somewhere they can read. The JSON branch
     * must not swallow the ordinary page redirect.
     */
    public function test_a_browser_request_still_redirects(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->subscription->forceFill([
            'status' => Subscription::STATUS_SUSPENDED,
        ])->save();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('settings.subscription.show'));
    }

    /**
     * Inertia sends `Accept: application/json` too, but it needs the
     * redirect — it is a page navigation, not a data fetch. Answering it
     * with 402 would leave the user staring at an unchanged screen.
     */
    public function test_an_inertia_navigation_still_redirects(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->subscription->forceFill([
            'status' => Subscription::STATUS_SUSPENDED,
        ])->save();

        $this->actingAs($owner)
            ->withHeaders(['X-Inertia' => 'true', 'Accept' => 'application/json'])
            ->get(route('dashboard'))
            ->assertRedirect(route('settings.subscription.show'));
    }

    public function test_an_active_business_polls_normally(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonStructure(['notifications', 'unread_count']);
    }
}
