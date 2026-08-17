<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Services\FinanceAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class FinanceAnalyticsServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private FinanceAnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analytics = app(FinanceAnalyticsService::class);
    }

    public function test_revenue_summary_only_counts_successful_transactions(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        PaymentTransaction::factory()->create(['business_id' => $business->id, 'amount' => 100000, 'status' => PaymentTransaction::STATUS_SUCCESSFUL, 'paid_at' => now()]);
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'amount' => 999999, 'status' => PaymentTransaction::STATUS_FAILED]);

        $summary = $this->analytics->revenueSummary();

        $this->assertSame(100000.0, $summary['total']);
        $this->assertSame(100000.0, $summary['today']);
    }

    public function test_transaction_counts_group_by_status(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        PaymentTransaction::factory()->create(['business_id' => $business->id, 'status' => PaymentTransaction::STATUS_SUCCESSFUL]);
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'status' => PaymentTransaction::STATUS_FAILED]);
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'status' => PaymentTransaction::STATUS_PENDING]);
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'status' => PaymentTransaction::STATUS_REFUNDED]);

        $counts = $this->analytics->transactionCounts();

        $this->assertSame(1, $counts['successful']);
        $this->assertSame(1, $counts['failed']);
        $this->assertSame(1, $counts['pending']);
        $this->assertSame(1, $counts['refunded']);
        $this->assertSame(0, $counts['chargebacks']);
    }

    public function test_commission_and_tax_only_sum_successful_transactions(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        PaymentTransaction::factory()->create([
            'business_id' => $business->id, 'status' => PaymentTransaction::STATUS_SUCCESSFUL,
            'commission_amount' => 500, 'tax_amount' => 1800, 'fee_amount' => 200,
        ]);
        PaymentTransaction::factory()->create([
            'business_id' => $business->id, 'status' => PaymentTransaction::STATUS_FAILED,
            'commission_amount' => 999, 'tax_amount' => 999, 'fee_amount' => 999,
        ]);

        $result = $this->analytics->commissionAndTax();

        $this->assertSame(500.0, $result['commission']);
        $this->assertSame(1800.0, $result['tax_collected']);
        $this->assertSame(200.0, $result['fees']);
    }

    public function test_top_businesses_orders_by_total_descending(): void
    {
        [, $businessA] = $this->createOwnerWithBusiness();
        [, $businessB] = $this->createOwnerWithBusiness();

        PaymentTransaction::factory()->create(['business_id' => $businessA->id, 'amount' => 50000, 'status' => PaymentTransaction::STATUS_SUCCESSFUL]);
        PaymentTransaction::factory()->create(['business_id' => $businessB->id, 'amount' => 150000, 'status' => PaymentTransaction::STATUS_SUCCESSFUL]);

        $top = $this->analytics->topBusinesses();

        $this->assertSame($businessB->id, $top[0]['business_id']);
        $this->assertSame(150000.0, $top[0]['total']);
    }

    public function test_gateway_performance_computes_success_rate(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $gateway = PaymentGateway::factory()->create();

        PaymentTransaction::factory()->create(['business_id' => $business->id, 'payment_gateway_id' => $gateway->id, 'status' => PaymentTransaction::STATUS_SUCCESSFUL, 'amount' => 1000]);
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'payment_gateway_id' => $gateway->id, 'status' => PaymentTransaction::STATUS_SUCCESSFUL, 'amount' => 1000]);
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'payment_gateway_id' => $gateway->id, 'status' => PaymentTransaction::STATUS_FAILED]);

        $performance = $this->analytics->gatewayPerformance();
        $row = collect($performance)->firstWhere('gateway_id', $gateway->id);

        $this->assertSame(2, $row['successful']);
        $this->assertSame(1, $row['failed']);
        $this->assertSame(66.7, $row['success_rate']);
    }
}
