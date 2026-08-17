<?php

namespace Tests\Unit\Finance;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Services\FinanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class FinanceReportServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private FinanceReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();

        // Report queries touch tenant-scoped models (Inventory, StockMovement);
        // the platform guard must be authenticated for BelongsToTenant to
        // bypass its scope, matching how these reports actually run in prod.
        Auth::guard('platform')->login(PlatformUser::factory()->create());

        $this->reports = app(FinanceReportService::class);
    }

    public function test_catalog_marks_sales_reports_unavailable(): void
    {
        $catalog = $this->reports->catalog();
        $sales = collect($catalog)->where('category', 'Sales');

        $this->assertTrue($sales->isNotEmpty());
        $sales->each(fn ($report) => $this->assertFalse($report['available']));
        $sales->each(fn ($report) => $this->assertNotNull($report['unavailable_reason']));
    }

    public function test_catalog_marks_financial_reports_available(): void
    {
        $catalog = $this->reports->catalog();
        $financial = collect($catalog)->where('category', 'Financial');

        $financial->each(fn ($report) => $this->assertTrue($report['available']));
    }

    public function test_revenue_report_aggregates_by_date(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        PaymentTransaction::factory()->create([
            'business_id' => $business->id, 'amount' => 75000,
            'status' => PaymentTransaction::STATUS_SUCCESSFUL, 'paid_at' => now(),
        ]);

        $result = $this->reports->generate('revenue');

        $this->assertSame(['Date', 'Transactions', 'Revenue'], $result['columns']);
        $this->assertSame(75000.0, $result['summary']['total_revenue']);
    }

    public function test_refunds_report_only_includes_refund_types(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        PaymentTransaction::factory()->create(['business_id' => $business->id, 'type' => PaymentTransaction::TYPE_REFUND, 'amount' => 20000]);
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'type' => PaymentTransaction::TYPE_SUBSCRIPTION_PAYMENT, 'amount' => 50000]);

        $result = $this->reports->generate('refunds');

        $this->assertCount(1, $result['rows']);
        $this->assertSame(20000.0, $result['summary']['total_refunded']);
    }

    public function test_unknown_report_key_returns_empty_result(): void
    {
        $result = $this->reports->generate('does-not-exist');

        $this->assertSame([], $result['columns']);
        $this->assertSame([], $result['rows']);
    }

    public function test_business_growth_report_counts_new_businesses_in_range(): void
    {
        $this->createOwnerWithBusiness();

        $result = $this->reports->generate('business_growth', [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(1, array_sum(array_column($result['rows'], 1)));
    }
}
