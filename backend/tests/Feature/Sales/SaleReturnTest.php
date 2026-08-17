<?php

namespace Tests\Feature\Sales;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\RBAC\Models\Role;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Sales\Services\SaleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * @return array{0: Branch, 1: Warehouse, 2: Product}
     */
    private function makeStockedProduct(string $businessId, float $stock = 20, float $price = 1000): array
    {
        $branch = Branch::query()->where('business_id', $businessId)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $businessId)->firstOrFail();
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => $price, 'cost_price' => $price * 0.6,
        ]);
        Inventory::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => $stock]);

        return [$branch, $warehouse, $product];
    }

    public function test_owner_can_request_and_approve_a_return_which_restocks_inventory(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, stock: 20, price: 1000);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payments' => [['amount' => 5000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);
        $this->assertSame('15.000', Inventory::where('product_id', $product->id)->first()->quantity);

        $saleItem = $sale->items->first();

        $this->actingAs($owner)->post("/sales/orders/{$sale->id}/returns", [
            'reason' => 'changed_mind',
            'refund_method' => 'cash',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity_returned' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $return = SaleReturn::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame(SaleReturn::STATUS_PENDING, $return->status);
        $this->assertSame('2000.00', $return->refund_amount);

        // Inventory unaffected until approval.
        $this->assertSame('15.000', Inventory::where('product_id', $product->id)->first()->quantity);

        $this->actingAs($owner)->post("/sales/returns/{$return->id}/approve")->assertSessionHasNoErrors();

        $this->assertSame(SaleReturn::STATUS_APPROVED, $return->refresh()->status);
        $this->assertSame('17.000', Inventory::where('product_id', $product->id)->first()->quantity);
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->where('type', StockMovement::TYPE_RETURN_IN)->count());
    }

    public function test_damaged_items_marked_no_restock_do_not_increase_inventory(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, stock: 20, price: 1000);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payments' => [['amount' => 5000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);
        $saleItem = $sale->items->first();

        $this->actingAs($owner)->post("/sales/orders/{$sale->id}/returns", [
            'reason' => 'damaged',
            'refund_method' => 'cash',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity_returned' => 2, 'condition' => 'damaged', 'restock' => false],
            ],
        ])->assertSessionHasNoErrors();

        $return = SaleReturn::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->actingAs($owner)->post("/sales/returns/{$return->id}/approve")->assertSessionHasNoErrors();

        $this->assertSame('15.000', Inventory::where('product_id', $product->id)->first()->quantity);
        $this->assertSame(0, StockMovement::where('product_id', $product->id)->where('type', StockMovement::TYPE_RETURN_IN)->count());
    }

    public function test_over_return_is_rejected(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, stock: 20, price: 1000);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['amount' => 3000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);
        $saleItem = $sale->items->first();

        $this->actingAs($owner)->post("/sales/orders/{$sale->id}/returns", [
            'reason' => 'changed_mind',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity_returned' => 999],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, SaleReturn::query()->count());
    }

    public function test_store_credit_refund_reduces_customer_debt_balance(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, stock: 20, price: 1000);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => Customer::TYPE_CREDIT, 'credit_limit' => 5000]);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'sold_by' => $owner->id,
        ]);
        $this->assertSame('5000.00', $customer->refresh()->current_balance);

        $saleItem = $sale->items->first();

        $this->actingAs($owner)->post("/sales/orders/{$sale->id}/returns", [
            'reason' => 'wrong_item',
            'refund_method' => 'store_credit',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity_returned' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $return = SaleReturn::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->actingAs($owner)->post("/sales/returns/{$return->id}/approve")->assertSessionHasNoErrors();

        // 5000 owed - 2000 store credit = 3000 still owed.
        $this->assertSame('3000.00', $customer->refresh()->current_balance);
    }

    public function test_rejecting_a_return_requires_a_reason_and_has_no_inventory_effect(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, stock: 20, price: 1000);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['amount' => 3000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);
        $saleItem = $sale->items->first();

        $this->actingAs($owner)->post("/sales/orders/{$sale->id}/returns", [
            'reason' => 'changed_mind',
            'items' => [['sale_item_id' => $saleItem->id, 'quantity_returned' => 1]],
        ]);
        $return = SaleReturn::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->actingAs($owner)->post("/sales/returns/{$return->id}/reject", [])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($owner)->post("/sales/returns/{$return->id}/reject", ['rejection_reason' => 'No receipt'])
            ->assertSessionHasNoErrors();

        $this->assertSame(SaleReturn::STATUS_REJECTED, $return->refresh()->status);
        $this->assertSame('17.000', Inventory::where('product_id', $product->id)->first()->quantity);
    }

    public function test_financial_report_subtracts_approved_refunds_from_revenue_and_cash(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, stock: 20, price: 1000);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payments' => [['amount' => 5000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);
        $saleItem = $sale->items->first();

        $this->actingAs($owner)->post("/sales/orders/{$sale->id}/returns", [
            'reason' => 'changed_mind',
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $saleItem->id, 'quantity_returned' => 2]],
        ]);
        $return = SaleReturn::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->actingAs($owner)->post("/sales/returns/{$return->id}/approve");

        $summary = app(\App\Domain\Accounting\Services\FinancialReportService::class)->summary($business->id);

        // Revenue 5000 - refund 2000 = 3000; cash balance 5000 - 2000 = 3000.
        $this->assertSame(3000.0, $summary['total_revenue']);
        $this->assertSame(2000.0, $summary['refunds_this_month']);
        $this->assertSame(3000.0, $summary['cash_balance']);
    }

    public function test_employee_without_sales_returns_create_permission_cannot_request_a_return(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id);

        $sale = Sale::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'sale_number' => 'SALE-TEST-1', 'total_amount' => 1000,
        ]);
        $saleItem = $sale->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000,
        ]);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create(['business_id' => $business->id, 'role_id' => $plainEmployeeRole->id]);

        $this->actingAs($employee)->post("/sales/orders/{$sale->id}/returns", [
            'reason' => 'changed_mind',
            'items' => [['sale_item_id' => $saleItem->id, 'quantity_returned' => 1]],
        ])->assertForbidden();

        $this->assertSame(0, SaleReturn::query()->count());
    }

    public function test_employee_without_approve_permission_cannot_approve(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id);

        $sale = Sale::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'sale_number' => 'SALE-TEST-2', 'total_amount' => 1000,
        ]);
        $sale->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000,
        ]);
        $return = SaleReturn::create([
            'business_id' => $business->id, 'sale_id' => $sale->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'return_number' => 'RET-TEST-1', 'reason' => SaleReturn::REASON_OTHER,
        ]);

        $cashierRole = Role::query()->where('business_id', $business->id)->where('slug', Role::CASHIER)->first();
        $cashier = User::factory()->create(['business_id' => $business->id, 'role_id' => $cashierRole->id]);

        $this->actingAs($cashier)->post("/sales/returns/{$return->id}/approve")->assertForbidden();
        $this->assertSame(SaleReturn::STATUS_PENDING, $return->refresh()->status);
    }
}
