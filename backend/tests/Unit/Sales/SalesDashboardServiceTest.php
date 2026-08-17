<?php

namespace Tests\Unit\Sales;

use App\Domain\Business\Models\Branch;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Services\SaleService;
use App\Domain\Sales\Services\SalesDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SalesDashboardServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private SalesDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SalesDashboardService::class);
    }

    public function test_summary_computes_real_profit_from_unit_cost_snapshots(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = $branch->warehouses()->firstOrFail();

        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 50]);

        app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payments' => [['amount' => 5000, 'payment_method' => 'cash']],
        ]);

        $summary = $this->service->summary($business->id);

        // 5 units * (1000 - 600) cost-vs-price = 2000 profit, tax excluded.
        $this->assertSame(2000.0, $summary['today_profit']);
        $this->assertSame(5000.0, $summary['today_revenue']);
        $this->assertSame(1, $summary['today_sales_count']);
    }

    public function test_summary_counts_only_active_customers(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        Customer::create(['business_id' => $business->id, 'name' => 'Active', 'is_active' => true]);
        Customer::create(['business_id' => $business->id, 'name' => 'Inactive', 'is_active' => false]);

        $summary = $this->service->summary($business->id);

        $this->assertSame(1, $summary['customers_count']);
    }

    public function test_sales_and_profit_trend_returns_fourteen_days_with_real_totals(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = $branch->warehouses()->firstOrFail();

        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 50]);

        app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['amount' => 2000, 'payment_method' => 'cash']],
        ]);

        $trend = $this->service->salesAndProfitTrend($business->id);

        $this->assertCount(14, $trend);
        $today = $trend[array_key_last($trend)];
        $this->assertSame(2000.0, $today['sales']);
        $this->assertSame(800.0, $today['profit']);
    }

    public function test_profit_is_zero_when_there_are_no_sales(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $summary = $this->service->summary($business->id);

        $this->assertSame(0.0, $summary['today_profit']);
        $this->assertSame(0.0, $summary['month_profit']);
    }
}
