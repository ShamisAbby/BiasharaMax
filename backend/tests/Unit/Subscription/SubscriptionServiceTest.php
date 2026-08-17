<?php

namespace Tests\Unit\Subscription;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_trial_sets_business_status_and_trial_end_date(): void
    {
        $owner = User::factory()->create();
        $business = Business::query()->create([
            'name' => 'Test Business',
            'slug' => 'test-business',
            'business_type' => 'retail',
            'email' => 'biz@example.com',
            'country' => 'KE',
            'currency' => 'KES',
            'owner_id' => $owner->getKey(),
            'status' => 'trial',
        ]);
        $plan = SubscriptionPlan::factory()->create(['trial_days' => 14]);

        $subscription = app(SubscriptionService::class)->startTrial($business, $plan);

        $this->assertSame(Subscription::STATUS_TRIALING, $subscription->status);
        $this->assertSame(Business::STATUS_TRIAL, $business->fresh()->status);
        $this->assertEqualsWithDelta(
            now()->addDays(14)->timestamp,
            $business->fresh()->trial_ends_at->timestamp,
            5,
        );
    }

    public function test_has_active_access_is_true_during_trial_and_false_after_expiry(): void
    {
        $owner = User::factory()->create();
        $business = Business::query()->create([
            'name' => 'Test Business',
            'slug' => 'test-business-2',
            'business_type' => 'retail',
            'email' => 'biz2@example.com',
            'country' => 'KE',
            'currency' => 'KES',
            'owner_id' => $owner->getKey(),
            'status' => 'trial',
        ]);
        $plan = SubscriptionPlan::factory()->create();
        $service = app(SubscriptionService::class);

        $service->startTrial($business, $plan);
        $this->assertTrue($service->hasActiveAccess($business->fresh()));

        $business->subscription->update(['trial_ends_at' => now()->subDay()]);
        $this->assertFalse($service->hasActiveAccess($business->fresh()));
    }
}
