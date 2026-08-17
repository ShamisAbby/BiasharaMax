<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SubscriptionPlanManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_superadmin_can_create_a_plan(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $response = $this->actingAs($platformUser, 'platform')->post(route('platform.subscriptions.plans.store'), [
            'name' => 'Custom Plan',
            'slug' => 'custom-plan',
            'type' => 'standard',
            'price_monthly' => 50000,
            'price_quarterly' => 140000,
            'price_yearly' => 500000,
            'trial_days' => 30,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'custom-plan']);
    }

    public function test_superadmin_can_deactivate_and_activate_a_plan(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.subscriptions.plans.deactivate', $plan->id))
            ->assertRedirect();
        $this->assertFalse($plan->fresh()->is_active);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.subscriptions.plans.activate', $plan->id))
            ->assertRedirect();
        $this->assertTrue($plan->fresh()->is_active);
    }

    public function test_plan_with_subscribers_cannot_be_deleted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $plan = $business->subscription->plan;

        $response = $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.subscriptions.plans.destroy', $plan->id));

        $response->assertSessionHasErrors('plan');
        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id]);
    }

    public function test_plan_without_subscribers_can_be_deleted(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.subscriptions.plans.destroy', $plan->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('subscription_plans', ['id' => $plan->id]);
    }
}
