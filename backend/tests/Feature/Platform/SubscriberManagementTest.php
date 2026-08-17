<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SubscriberManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_superadmin_can_assign_a_plan_to_a_subscriber(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $newPlan = SubscriptionPlan::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.subscriptions.subscribers.assign-plan', $business->subscription->id), [
                'subscription_plan_id' => $newPlan->id,
                'billing_cycle' => 'yearly',
            ])
            ->assertRedirect();

        $subscription = $business->subscription->fresh();
        $this->assertSame($newPlan->id, $subscription->subscription_plan_id);
        $this->assertSame('yearly', $subscription->billing_cycle);
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
    }

    public function test_superadmin_can_extend_a_trial(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $originalTrialEnd = $business->subscription->trial_ends_at;

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.subscriptions.subscribers.extend-trial', $business->subscription->id), [
                'days' => 15,
            ])
            ->assertRedirect();

        $this->assertTrue($business->subscription->fresh()->trial_ends_at->gt($originalTrialEnd));
    }

    public function test_superadmin_can_record_a_renewal_payment(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.subscriptions.subscribers.renew', $business->subscription->id), [
                'amount' => 35000,
                'payment_method' => 'mobile_money',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subscription_transactions', [
            'business_id' => $business->id,
            'amount' => 35000,
            'payment_method' => 'mobile_money',
        ]);
    }

    public function test_superadmin_can_suspend_and_restore_a_subscriber(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.subscriptions.subscribers.suspend', $business->subscription->id))
            ->assertRedirect();
        $this->assertSame(Subscription::STATUS_SUSPENDED, $business->subscription->fresh()->status);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.subscriptions.subscribers.restore', $business->subscription->id))
            ->assertRedirect();
        $this->assertNotSame(Subscription::STATUS_SUSPENDED, $business->subscription->fresh()->status);
    }

    public function test_superadmin_can_cancel_a_subscriber(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.subscriptions.subscribers.cancel', $business->subscription->id))
            ->assertRedirect();

        $this->assertSame(Subscription::STATUS_CANCELED, $business->subscription->fresh()->status);
    }
}
