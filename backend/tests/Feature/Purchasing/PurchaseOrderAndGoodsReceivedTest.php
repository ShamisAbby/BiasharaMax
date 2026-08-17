<?php

namespace Tests\Feature\Purchasing;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PurchaseOrderAndGoodsReceivedTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * @return array{0: Branch, 1: Warehouse, 2: Supplier, 3: Product}
     */
    private function makeFixtures(string $businessId): array
    {
        $branch = Branch::query()->where('business_id', $businessId)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $businessId)->firstOrFail();
        $supplier = Supplier::create(['business_id' => $businessId, 'name' => 'Acme Supplies', 'status' => Supplier::STATUS_ACTIVE]);
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);

        return [$branch, $warehouse, $supplier, $product];
    }

    public function test_owner_can_create_a_purchase_order_with_computed_totals(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $supplier, $product] = $this->makeFixtures($business->id);

        $this->actingAs($owner)->post('/purchasing/orders', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 500, 'tax_amount' => 50],
            ],
            'shipping_cost' => 20,
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $po->status);
        $this->assertSame('5000.00', $po->subtotal);
        $this->assertSame('50.00', $po->tax_amount);
        // total = subtotal(5000) - discount(0) + tax(50) + shipping(20) + other(0)
        $this->assertSame('5070.00', $po->total_amount);
    }

    public function test_full_lifecycle_submit_approve_send(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $supplier, $product] = $this->makeFixtures($business->id);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-TEST-1', 'order_date' => now()->toDateString(),
        ]);
        $po->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'product_sku' => $product->sku,
            'quantity_ordered' => 5, 'unit_cost' => 500, 'line_total' => 2500,
        ]);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::STATUS_PENDING_APPROVAL, $po->refresh()->status);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/approve")->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->status);
        $this->assertSame($owner->id, $po->approved_by);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/send")->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::STATUS_SENT, $po->refresh()->status);
    }

    public function test_rejecting_a_pending_approval_order_requires_a_reason(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $supplier, $product] = $this->makeFixtures($business->id);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-TEST-2', 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
        ]);
        $po->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity_ordered' => 5, 'unit_cost' => 500, 'line_total' => 2500,
        ]);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/reject", [])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/reject", ['rejection_reason' => 'Too expensive'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PurchaseOrder::STATUS_REJECTED, $po->refresh()->status);
    }

    public function test_receiving_goods_updates_inventory_and_creates_a_stock_movement_and_expense(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $supplier, $product] = $this->makeFixtures($business->id);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-TEST-3', 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_SENT, 'sent_at' => now(),
        ]);
        $item = $po->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity_ordered' => 10, 'unit_cost' => 500, 'line_total' => 5000,
        ]);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/receive", [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['purchase_order_item_id' => $item->id, 'quantity_received' => 10],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('10.000', Inventory::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first()->quantity);
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->where('type', StockMovement::TYPE_PURCHASE)->count());
        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $po->refresh()->status);

        $expense = Expense::query()->where('business_id', $business->id)->where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($expense);
        $this->assertSame('5000.00', $expense->amount);
        $this->assertSame(Expense::STATUS_APPROVED, $expense->status);
    }

    public function test_partial_receipt_marks_order_partially_received_and_tracks_batch(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $supplier, $product] = $this->makeFixtures($business->id);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-TEST-4', 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_SENT, 'sent_at' => now(),
        ]);
        $item = $po->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity_ordered' => 10, 'unit_cost' => 500, 'line_total' => 5000,
        ]);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/receive", [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['purchase_order_item_id' => $item->id, 'quantity_received' => 6, 'batch_number' => 'BATCH-1', 'expiry_date' => now()->addYear()->toDateString()],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->refresh()->status);
        $this->assertSame('4.000', $item->refresh()->remainingQuantity());
        $this->assertSame(1, ProductBatch::where('batch_number', 'BATCH-1')->count());
    }

    public function test_over_delivery_is_rejected(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $supplier, $product] = $this->makeFixtures($business->id);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-TEST-5', 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_SENT, 'sent_at' => now(),
        ]);
        $item = $po->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity_ordered' => 5, 'unit_cost' => 500, 'line_total' => 2500,
        ]);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/receive", [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['purchase_order_item_id' => $item->id, 'quantity_received' => 999],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, GoodsReceivedNote::query()->count());
        $this->assertSame('0.000', $item->refresh()->quantity_received);
    }

    public function test_employee_without_purchase_orders_create_permission_cannot_create_one(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $supplier, $product] = $this->makeFixtures($business->id);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create(['business_id' => $business->id, 'role_id' => $plainEmployeeRole->id]);

        $this->actingAs($employee)->post('/purchasing/orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity_ordered' => 1, 'unit_cost' => 100]],
        ])->assertForbidden();

        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_employee_without_approve_permission_cannot_approve(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $supplier, $product] = $this->makeFixtures($business->id);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-TEST-6', 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
        ]);

        $purchasingOfficerRole = Role::query()->where('business_id', $business->id)->where('slug', Role::PURCHASING_OFFICER)->first();
        $officer = User::factory()->create(['business_id' => $business->id, 'role_id' => $purchasingOfficerRole->id]);

        $this->actingAs($officer)->post("/purchasing/orders/{$po->id}/approve")->assertForbidden();
        $this->assertSame(PurchaseOrder::STATUS_PENDING_APPROVAL, $po->refresh()->status);
    }
}
