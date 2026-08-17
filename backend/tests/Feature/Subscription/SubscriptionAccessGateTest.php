<?php

namespace Tests\Feature\Subscription;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SubscriptionAccessGateTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_owner_with_active_trial_can_access_the_dashboard(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    }

    public function test_owner_with_a_locked_subscription_is_redirected_to_the_subscription_page(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $business->subscription->forceFill([
            'status' => Subscription::STATUS_SUSPENDED,
        ])->save();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('settings.subscription.show'));
    }

    public function test_owner_with_a_locked_subscription_can_still_view_the_subscription_page(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $business->subscription->forceFill([
            'status' => Subscription::STATUS_SUSPENDED,
        ])->save();

        $this->actingAs($owner)
            ->get(route('settings.subscription.show'))
            ->assertOk();
    }

    public function test_owner_in_grace_period_can_still_access_the_dashboard(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $business->subscription->forceFill([
            'status' => Subscription::STATUS_EXPIRED,
            'grace_period_ends_at' => now()->addDays(5),
        ])->save();

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    }
}
