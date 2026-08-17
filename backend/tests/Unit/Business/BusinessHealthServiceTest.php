<?php

namespace Tests\Unit\Business;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Services\BusinessHealthService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Services\SaleService;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BusinessHealthServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private BusinessHealthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BusinessHealthService::class);
    }

    public function test_a_brand_new_business_scores_one_hundred(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $health = $this->service->compute($business->refresh());

        $this->assertSame(100, $health['score']);
        $this->assertSame('Excellent', $health['status']);
        $this->assertSame([], $health['signals']);
        $this->assertSame(['Everything looks healthy — keep up the great work!'], $health['recommendations']);
    }

    public function test_outstanding_customer_debt_deducts_points_and_recommends_follow_up(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = $branch->warehouses()->firstOrFail();

        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 50]);

        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Debtor', 'customer_type' => 'credit', 'credit_limit' => 100000]);
        app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id, 'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $health = $this->service->compute($business->refresh());

        $this->assertLessThan(100, $health['score']);
        $this->assertContains(
            'Outstanding customer debt is significant relative to recent revenue — consider following up on overdue balances.',
            $health['recommendations'],
        );
    }

    public function test_a_suspended_subscription_deducts_points_and_recommends_attention(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $business->subscription->update(['status' => Subscription::STATUS_SUSPENDED]);

        $health = $this->service->compute($business->refresh());

        $this->assertSame(80, $health['score']);
        $this->assertContains(
            'Your subscription needs attention — check your billing to avoid service interruption.',
            $health['recommendations'],
        );
    }

    public function test_out_of_stock_products_are_recommended_before_low_stock(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = $branch->warehouses()->firstOrFail();

        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Out of Stock Item', 'slug' => 'oos-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'reorder_level' => 5,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 0, 'reorder_level' => 5]);

        $health = $this->service->compute($business->refresh());

        $this->assertSame('1 product(s) are out of stock — restock soon to avoid lost sales.', $health['recommendations'][0]);
    }
}
