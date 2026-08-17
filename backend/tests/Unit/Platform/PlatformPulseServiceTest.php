<?php

namespace Tests\Unit\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\Business;
use App\Domain\Platform\Services\PlatformPulseService;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PlatformPulseServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private PlatformPulseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlatformPulseService::class);
    }

    public function test_kpis_report_real_counts_with_a_fourteen_day_trend(): void
    {
        $this->createOwnerWithBusiness();
        $this->createOwnerWithBusiness();

        $kpis = $this->service->kpis();

        $this->assertSame(2, $kpis['total_businesses']['value']);
        $this->assertCount(14, $kpis['total_businesses']['trend']);
        $this->assertSame(2, array_sum($kpis['total_businesses']['trend']));
    }

    public function test_mrr_and_arr_are_computed_from_active_monthly_subscriptions(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::factory()->create(['price_monthly' => 50000]);

        $business->subscription->update([
            'subscription_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $kpis = $this->service->kpis();

        $this->assertSame(50000.0, $kpis['mrr']['value']);
        $this->assertSame(600000.0, $kpis['arr']['value']);
    }

    public function test_trialing_subscriptions_do_not_count_toward_mrr(): void
    {
        $this->createOwnerWithBusiness();

        $kpis = $this->service->kpis();

        $this->assertSame(0.0, $kpis['mrr']['value']);
    }

    public function test_business_pulse_reports_security_and_health_scores(): void
    {
        $pulse = $this->service->businessPulse();

        $this->assertSame(100.0, $pulse['security_score']);
        $this->assertContains($pulse['system_health_label'], ['Excellent', 'Good', 'Needs Attention', 'Critical']);
        $this->assertFalse($pulse['ai_configured']);
        $this->assertSame([], $pulse['ai_recommendations']->all());
    }

    public function test_live_activity_resolves_the_acting_platform_user_name(): void
    {
        $platformUser = PlatformUser::factory()->create(['name' => 'Ada Lovelace']);
        $this->actingAs($platformUser, 'platform');

        Business::query()->first()?->delete();
        $this->createOwnerWithBusiness();

        $activity = $this->service->liveActivity(5);

        $this->assertNotEmpty($activity);
        $this->assertSame('Ada Lovelace', $activity[0]['actor_name']);
        $this->assertSame('platform_user', $activity[0]['actor_type']);
    }
}
