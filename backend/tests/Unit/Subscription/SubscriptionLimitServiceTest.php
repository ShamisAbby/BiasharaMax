<?php

namespace Tests\Unit\Subscription;

use App\Domain\Business\Models\Branch;
use App\Domain\Subscription\Exceptions\PlanLimitExceededException;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionLimitService;
use App\Domain\Subscription\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SubscriptionLimitServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_ensure_can_add_throws_once_the_plan_limit_is_reached(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::factory()->create(['max_branches' => 1]);
        app(SubscriptionService::class)->changePlan($business->subscription, $plan, 'monthly');

        // createOwnerWithBusiness() already provisions one main branch.
        $this->expectException(PlanLimitExceededException::class);

        app(SubscriptionLimitService::class)->ensureCanAdd($business, 'branches');
    }

    public function test_ensure_can_add_passes_when_under_the_limit(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::factory()->create(['max_branches' => 5]);
        app(SubscriptionService::class)->changePlan($business->subscription, $plan, 'monthly');

        app(SubscriptionLimitService::class)->ensureCanAdd($business, 'branches');

        $this->assertSame(1, Branch::query()->where('business_id', $business->id)->count());
    }

    public function test_null_limit_means_unlimited(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::factory()->create(['max_branches' => null]);
        app(SubscriptionService::class)->changePlan($business->subscription, $plan, 'monthly');

        app(SubscriptionLimitService::class)->ensureCanAdd($business, 'branches');

        $this->assertTrue(true);
    }
}
