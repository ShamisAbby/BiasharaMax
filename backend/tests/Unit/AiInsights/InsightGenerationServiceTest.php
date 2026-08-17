<?php

namespace Tests\Unit\AiInsights;

use App\Domain\AiInsights\Services\InsightGenerationService;
use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class InsightGenerationServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private InsightGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InsightGenerationService::class);
    }

    public function test_revenue_forecast_extrapolates_an_ascending_trend(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        // 0, 1, 2, 3, 4, 5 months ago -> amounts 100..600 ascending toward "now".
        for ($i = 5; $i >= 0; $i--) {
            $paidAt = Carbon::now()->subMonths($i)->startOfMonth()->addDays(2);
            $amount = (6 - $i) * 100;
            PaymentTransaction::factory()->create([
                'business_id' => $business->id,
                'status' => PaymentTransaction::STATUS_SUCCESSFUL,
                'amount' => $amount,
                'paid_at' => $paidAt,
            ]);
        }

        $forecast = $this->service->revenueForecast();

        $this->assertCount(6, $forecast['history']);
        $this->assertSame('linear_trend_extrapolation', $forecast['method']);
        // Perfectly linear series 100..600 -> next value extrapolates to 700.
        $this->assertEquals(700.0, $forecast['forecast_next_month']);
    }

    public function test_subscription_forecast_counts_subscriptions_created_per_month(): void
    {
        $this->createOwnerWithBusiness();
        $this->createOwnerWithBusiness();

        $forecast = $this->service->subscriptionForecast();

        $this->assertCount(6, $forecast['history']);
        $this->assertSame('linear_trend_extrapolation', $forecast['method']);
        $currentMonthKey = Carbon::now()->format('M Y');
        $this->assertSame(2, $forecast['history'][$currentMonthKey]);
    }

    public function test_churn_risk_respects_the_limit_and_scores_inactive_businesses_higher(): void
    {
        [$inactiveOwner, $inactiveBusiness] = $this->createOwnerWithBusiness();
        [$activeOwner, $activeBusiness] = $this->createOwnerWithBusiness();

        $inactiveBusiness->forceFill(['status' => Business::STATUS_ACTIVE])->save();
        $activeBusiness->forceFill(['status' => Business::STATUS_ACTIVE])->save();
        $inactiveOwner->forceFill(['last_login_at' => now()->subDays(60)])->save();
        $activeOwner->forceFill(['last_login_at' => now()])->save();

        PaymentTransaction::factory()->create([
            'business_id' => $activeBusiness->id,
            'status' => PaymentTransaction::STATUS_SUCCESSFUL,
            'paid_at' => now(),
        ]);

        $risk = $this->service->churnRisk(10);
        $riskByBusiness = collect($risk)->keyBy('business_id');

        $this->assertTrue($riskByBusiness->has($inactiveBusiness->id));
        $this->assertGreaterThan(0, $riskByBusiness[$inactiveBusiness->id]['risk_score']);

        $limited = $this->service->churnRisk(1);
        $this->assertLessThanOrEqual(1, count($limited));
    }

    public function test_business_health_scores_are_inverse_of_churn_risk(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $business->forceFill(['status' => Business::STATUS_ACTIVE])->save();
        $owner->forceFill(['last_login_at' => now()->subDays(60)])->save();
        PaymentTransaction::factory()->create([
            'business_id' => $business->id,
            'status' => PaymentTransaction::STATUS_SUCCESSFUL,
            'paid_at' => now(),
        ]);

        $scores = $this->service->businessHealthScores();
        $row = collect($scores)->firstWhere('business_id', $business->id);

        $this->assertNotNull($row);
        // No login in >30 days (-40) but a recent successful payment, no grace period -> health 60.
        $this->assertSame(60, $row['health_score']);
    }

    public function test_growth_trend_compares_this_month_to_last_month(): void
    {
        $this->createOwnerWithBusiness();

        $trend = $this->service->growthTrend();

        $this->assertArrayHasKey('this_month', $trend);
        $this->assertGreaterThanOrEqual(1, $trend['this_month']['businesses']);
    }

    public function test_most_active_businesses_orders_by_transaction_count_descending(): void
    {
        [, $businessA] = $this->createOwnerWithBusiness();
        [, $businessB] = $this->createOwnerWithBusiness();

        PaymentTransaction::factory()->count(3)->create(['business_id' => $businessA->id, 'created_at' => now()]);
        PaymentTransaction::factory()->count(1)->create(['business_id' => $businessB->id, 'created_at' => now()]);

        $active = $this->service->mostActiveBusinesses();

        $this->assertSame($businessA->id, $active[0]['business_id']);
        $this->assertSame(3, $active[0]['transaction_count']);
    }

    public function test_inactive_businesses_orders_by_days_inactive_descending(): void
    {
        [$ownerA, $businessA] = $this->createOwnerWithBusiness();
        [$ownerB, $businessB] = $this->createOwnerWithBusiness();

        $businessA->forceFill(['status' => Business::STATUS_ACTIVE])->save();
        $businessB->forceFill(['status' => Business::STATUS_ACTIVE])->save();
        $ownerA->forceFill(['last_login_at' => now()->subDays(5)])->save();
        $ownerB->forceFill(['last_login_at' => now()->subDays(90)])->save();

        $inactive = $this->service->inactiveBusinesses();

        $this->assertSame($businessB->id, $inactive[0]['business_id']);
        $this->assertEqualsWithDelta(90, $inactive[0]['days_inactive'], 1);
    }

    public function test_revenue_by_business_type_groups_and_sums_successful_transactions(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        PaymentTransaction::factory()->create([
            'business_id' => $business->id, 'status' => PaymentTransaction::STATUS_SUCCESSFUL, 'amount' => 500,
        ]);
        PaymentTransaction::factory()->create([
            'business_id' => $business->id, 'status' => PaymentTransaction::STATUS_FAILED, 'amount' => 999999,
        ]);

        $byType = $this->service->revenueByBusinessType();

        $this->assertNotEmpty($byType);
        $total = collect($byType)->sum('total');
        $this->assertSame(500.0, $total);
    }

    public function test_revenue_by_country_groups_and_sums_successful_transactions(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $business->forceFill(['country' => 'TZ'])->save();

        PaymentTransaction::factory()->create([
            'business_id' => $business->id, 'status' => PaymentTransaction::STATUS_SUCCESSFUL, 'amount' => 750,
        ]);

        $byCountry = $this->service->revenueByCountry();
        $row = collect($byCountry)->firstWhere('country', 'TZ');

        $this->assertNotNull($row);
        $this->assertSame(750.0, $row['total']);
    }
}
