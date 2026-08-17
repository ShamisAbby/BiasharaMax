<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_superadmin_can_list_all_businesses(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $businessOne] = $this->createOwnerWithBusiness();
        [, $businessTwo] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.businesses.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Businesses/Index')
                ->has('businesses.data', 2)
            );

        $this->assertNotNull($businessOne);
        $this->assertNotNull($businessTwo);
    }

    public function test_tenant_user_cannot_access_the_platform_businesses_list(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.businesses.index'))
            ->assertRedirect(route('platform.login'));
    }

    public function test_superadmin_can_suspend_and_activate_a_business(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.businesses.suspend', $business->id))
            ->assertRedirect();

        $this->assertSame(Business::STATUS_SUSPENDED, $business->fresh()->status);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.businesses.activate', $business->id))
            ->assertRedirect();

        $this->assertSame(Business::STATUS_ACTIVE, $business->fresh()->status);
    }

    public function test_superadmin_can_update_a_businesses_subscription(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $newPlan = SubscriptionPlan::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->patch(route('platform.businesses.subscription.update', $business->id), [
                'subscription_plan_id' => $newPlan->id,
                'status' => Subscription::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $subscription = $business->fresh()->subscription;
        $this->assertSame($newPlan->id, $subscription->subscription_plan_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
    }
}
