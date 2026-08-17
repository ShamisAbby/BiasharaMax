<?php

namespace Tests\Unit\Subscription;

use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Models\SubscriptionTransaction;
use App\Domain\Subscription\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
    }

    public function test_changing_plan_sets_an_active_billing_period(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $newPlan = SubscriptionPlan::factory()->create();
        $subscription = $business->subscription;

        $updated = $this->service->changePlan($subscription, $newPlan, 'yearly');

        $this->assertSame($newPlan->id, $updated->subscription_plan_id);
        $this->assertSame('yearly', $updated->billing_cycle);
        $this->assertSame(Subscription::STATUS_ACTIVE, $updated->status);
        $this->assertNotNull($updated->current_period_end);
        $this->assertNull($updated->trial_ends_at);
        $this->assertSame(Business::STATUS_ACTIVE, $business->fresh()->status);
    }

    public function test_changing_plan_with_a_custom_price_overrides_the_catalog_price(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::factory()->create(['price_monthly' => 50000]);
        $subscription = $business->subscription;

        $updated = $this->service->changePlan($subscription, $plan, 'monthly', 30000.0);

        $this->assertSame(30000.0, $updated->effectivePrice());
    }

    public function test_renew_with_payment_creates_a_transaction_and_extends_the_period(): void
    {
        Notification::fake();

        [, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->service->changePlan($business->subscription, $plan, 'monthly');
        $subscription->forceFill(['current_period_end' => now()->subDays(5)])->save();

        $transaction = $this->service->renewWithPayment($subscription, [
            'amount' => 50000,
            'payment_method' => 'cash',
        ]);

        $this->assertInstanceOf(SubscriptionTransaction::class, $transaction);
        $this->assertSame(50000.0, (float) $transaction->amount);
        $this->assertTrue($subscription->fresh()->current_period_end->isFuture());
    }

    public function test_suspend_locks_the_business_immediately(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $subscription = $business->subscription;

        $this->service->suspend($subscription);

        $this->assertTrue($subscription->fresh()->isLocked());
        $this->assertSame(Business::STATUS_SUSPENDED, $business->fresh()->status);
    }

    public function test_restore_reactivates_a_suspended_subscription(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $subscription = $business->subscription;
        $this->service->suspend($subscription);

        $this->service->restore($subscription);

        $this->assertFalse($subscription->fresh()->isLocked());
    }

    public function test_extend_trial_pushes_trial_end_date_forward(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $subscription = $business->subscription;
        $originalTrialEnd = $subscription->trial_ends_at;

        $this->service->extendTrial($subscription, 10);

        $this->assertTrue($subscription->fresh()->trial_ends_at->gt($originalTrialEnd));
    }

    public function test_expire_starts_the_grace_period_and_locks_business_after_it_passes(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $subscription = $business->subscription;

        $this->service->expire($subscription);
        $fresh = $subscription->fresh();

        $this->assertSame(Subscription::STATUS_EXPIRED, $fresh->status);
        $this->assertTrue($fresh->isInGracePeriod());
        $this->assertFalse($fresh->isLocked());

        $fresh->forceFill(['grace_period_ends_at' => now()->subDay()])->save();
        $this->assertTrue($fresh->fresh()->isLocked());
    }
}
