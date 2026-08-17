<?php

namespace Tests\Feature\Platform;

use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Services\DesktopEntitlementService;
use App\Domain\Subscription\Services\SubscriptionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Suspending a business must actually suspend it.
 *
 * It did not. `suspend` wrote `businesses.status = 'suspended'`, the admin
 * table rendered a red badge, and no access check anywhere read the
 * column: `hasActiveAccess()` consulted only the subscription, and the
 * subscription was still perfectly valid. The owner carried on trading
 * while the platform displayed "Suspended" to the one group of people who
 * could not tell it was untrue.
 *
 * The dependency between the two ran one way — a suspended *subscription*
 * marked the business suspended — which is probably why the reverse
 * looked covered. It was not.
 *
 * These tests assert the outcome (locked out) rather than the mechanism,
 * so they keep their meaning if the check moves somewhere else.
 */
class BusinessSuspensionTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_a_suspended_business_loses_access_even_with_a_valid_subscription(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        // The subscription is untouched throughout — that is the point.
        $this->assertTrue(app(SubscriptionService::class)->hasActiveAccess($business));

        $business->update(['status' => Business::STATUS_SUSPENDED]);

        $this->assertFalse(
            app(SubscriptionService::class)->hasActiveAccess($business->fresh()),
            'A suspended business kept its access because only the subscription was checked.',
        );
    }

    public function test_a_suspended_owner_is_redirected_off_the_dashboard(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();

        $business->update(['status' => Business::STATUS_SUSPENDED]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('settings.subscription.show'))
            ->assertSessionHas('status', 'business-suspended');
    }

    /**
     * The reason has to survive the redirect. Sending a suspended owner to
     * a page that says "your subscription has ended" invites them to pay
     * for something that will not help.
     */
    public function test_the_reason_given_distinguishes_suspension_from_expiry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->update(['status' => Business::STATUS_SUSPENDED]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertSessionHas('status', 'business-suspended');
    }

    /**
     * The till is the surface where a missed suspension costs the most,
     * because it is the one taking money.
     */
    public function test_a_suspended_business_cannot_use_the_desktop_till(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $before = app(DesktopEntitlementService::class)->describe($business);
        $this->assertTrue($before['allowed']);

        $business->update(['status' => Business::STATUS_SUSPENDED]);

        $after = app(DesktopEntitlementService::class)->describe($business->fresh());

        $this->assertFalse($after['allowed']);
        $this->assertSame(DesktopEntitlementService::STATE_LOCKED, $after['state']);

        // A product key does not undo a suspension, so the app must not
        // offer the box that implies it does.
        $this->assertFalse($after['requires_product_key']);
        $this->assertFalse($after['can_start_trial']);
    }

    public function test_reactivating_restores_access(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->update(['status' => Business::STATUS_SUSPENDED]);
        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect();

        $business->update(['status' => Business::STATUS_ACTIVE]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    }

    /**
     * A trial business is not a suspended one. Guarding on "not active"
     * rather than on the two blocking statuses would lock out every
     * business still in its first 30 days.
     */
    public function test_a_trial_business_still_has_access(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->update(['status' => Business::STATUS_TRIAL]);

        $this->assertTrue(app(SubscriptionService::class)->hasActiveAccess($business->fresh()));
        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    }
}
