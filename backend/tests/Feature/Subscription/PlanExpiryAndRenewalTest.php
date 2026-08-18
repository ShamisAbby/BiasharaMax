<?php

namespace Tests\Feature\Subscription;

use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Notifications\SubscriptionExpiringSoonNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * An expired plan is an invoice. A suspension is an accusation.
 *
 * They were briefly the same thing here: `isBlockedByPlatform()` counted
 * `expired` alongside `suspended`, so a customer whose plan simply ran out
 * was shown "This account is temporarily suspended" and pointed at
 * support — no renew button anywhere, and an implication they had done
 * something wrong. Both states end in "no access", which is exactly why
 * the distinction has to be tested rather than assumed.
 */
class PlanExpiryAndRenewalTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    private function expire(Business $business): void
    {
        $business->subscription->forceFill([
            'status' => Subscription::STATUS_EXPIRED,
            'current_period_end' => Carbon::now()->subDays(30),
            'grace_period_ends_at' => Carbon::now()->subDays(20),
        ])->save();

        $business->forceFill(['status' => Business::STATUS_EXPIRED])->save();
    }

    public function test_an_expired_plan_does_not_say_suspended(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->expire($business);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('plan.expired'));
    }

    public function test_the_expired_page_offers_the_plans(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->expire($business);

        $this->actingAs($owner)
            ->get(route('plan.expired'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('PlanExpired')
                ->has('plans', 3)
                ->has('businessName'));
    }

    /**
     * The rule this whole flow exists to protect. A renewal must never
     * hand out another free trial, or a customer can use the product
     * indefinitely by letting the plan lapse every month.
     */
    public function test_renewing_never_grants_another_trial(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->expire($business);

        $plan = SubscriptionPlan::query()->where('slug', 'quarterly')->first();

        $this->actingAs($owner)->post(route('subscription.renew', $plan));

        $subscription = $business->fresh()->subscription;

        $this->assertSame(Subscription::STATUS_PENDING_PAYMENT, $subscription->status);
        $this->assertNull($subscription->trial_ends_at, 'Renewal handed out a trial.');
    }

    public function test_a_renewal_does_not_grant_access_before_payment(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->expire($business);
        $plan = SubscriptionPlan::query()->where('slug', 'yearly')->first();

        $this->actingAs($owner)->post(route('subscription.renew', $plan));

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('plan.expired'));
    }

    /**
     * A stale grace window from the previous term must not let the account
     * back in while the renewal is unpaid.
     */
    public function test_renewal_clears_the_old_grace_period(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->subscription->forceFill([
            'status' => Subscription::STATUS_EXPIRED,
            'current_period_end' => Carbon::now()->subDay(),
            'grace_period_ends_at' => Carbon::now()->addDays(5),
        ])->save();

        $plan = SubscriptionPlan::query()->where('slug', 'quarterly')->first();

        $this->actingAs($owner)->post(route('subscription.renew', $plan));

        $this->assertNull($business->fresh()->subscription->grace_period_ends_at);
    }

    public function test_a_suspended_business_is_not_offered_a_renewal(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->update(['status' => Business::STATUS_SUSPENDED]);

        $this->actingAs($owner)
            ->get(route('plan.expired'))
            ->assertRedirect(route('suspended'));
    }

    public function test_an_active_business_cannot_open_the_expired_page(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('plan.expired'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_owner_is_warned_thirty_days_out(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->subscription->forceFill([
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => Carbon::now()->addDays(30),
        ])->save();

        $this->artisan('subscriptions:check-expirations')->assertSuccessful();

        Notification::assertSentTo($owner, SubscriptionExpiringSoonNotification::class);
    }

    /**
     * Not every day for a month. A daily reminder teaches the recipient to
     * delete mail from this sender unread, which does its damage on the
     * one day the message matters.
     */
    public function test_no_reminder_on_a_day_that_is_not_a_threshold(): void
    {
        Notification::fake();

        [, $business] = $this->createOwnerWithBusiness();

        $business->subscription->forceFill([
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => Carbon::now()->addDays(21),
        ])->save();

        $this->artisan('subscriptions:check-expirations')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /**
     * The mapping that caused the wrong page to show.
     *
     * `syncBusinessStatus()` wrote `suspended` for a cancelled
     * subscription. Cancelling is a billing outcome; suspension is a
     * decision about the account. Once that word was in the column, every
     * reader downstream believed it — the access gate was working
     * correctly on data that was already wrong.
     */
    public function test_a_cancelled_subscription_does_not_mark_the_business_suspended(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        app(\App\Domain\Subscription\Services\SubscriptionService::class)
            ->cancel($business->subscription);

        $this->assertNotSame(
            Business::STATUS_SUSPENDED,
            $business->fresh()->status,
            'Cancelling a subscription branded the business as suspended.',
        );
    }

    /**
     * A platform suspension outranks billing. Without this, expiring or
     * renewing a suspended business would overwrite the suspension and
     * quietly hand access back.
     */
    public function test_billing_changes_cannot_lift_a_platform_suspension(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->update(['status' => Business::STATUS_SUSPENDED]);

        app(\App\Domain\Subscription\Services\SubscriptionService::class)
            ->expire($business->subscription);

        $this->assertSame(Business::STATUS_SUSPENDED, $business->fresh()->status);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('suspended'));
    }
}
