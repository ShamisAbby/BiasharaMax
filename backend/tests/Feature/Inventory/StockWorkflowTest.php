<?php

namespace Tests\Feature\Inventory;

use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Services\InventoryCountService;
use App\Domain\Inventory\Services\ProductService;
use App\Domain\Inventory\Services\StockAdjustmentService;
use App\Domain\Inventory\Services\StockMovementService;
use App\Domain\Inventory\Services\StockTransferService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class StockWorkflowTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_and_complete_a_stock_adjustment(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->stockedProduct($business->id, $warehouse, $branch, $owner, 100);

        $this->actingAs($owner)->post('/inventory/stock-adjustments', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'reason' => 'damage',
            'items' => [['product_id' => $product->id, 'direction' => 'out', 'quantity' => 5]],
        ])->assertSessionHasNoErrors();

        $adjustment = StockAdjustment::query()->where('business_id', $business->id)->first();
        $this->assertSame(StockAdjustment::STATUS_DRAFT, $adjustment->status);

        $this->actingAs($owner)->post("/inventory/stock-adjustments/{$adjustment->id}/complete")
            ->assertSessionHasNoErrors();

        $this->assertSame(StockAdjustment::STATUS_COMPLETED, $adjustment->fresh()->status);
        $inventory = Inventory::query()->where('product_id', $product->id)->first();
        $this->assertSame('95.000', $inventory->quantity);
    }

    public function test_completed_adjustment_cannot_be_deleted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->stockedProduct($business->id, $warehouse, $branch, $owner, 50);

        $adjustment = app(StockAdjustmentService::class)->create($business->id, $owner, [
            'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'reason' => 'correction',
            'items' => [['product_id' => $product->id, 'direction' => 'in', 'quantity' => 5]],
        ]);
        app(StockAdjustmentService::class)->complete($adjustment, $owner);

        $this->actingAs($owner)->delete("/inventory/stock-adjustments/{$adjustment->id}")
            ->assertForbidden();
    }

    public function test_full_stock_transfer_lifecycle_moves_stock_between_warehouses(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = $business->branches->first();
        $warehouseA = $business->warehouses->first();
        $warehouseB = Warehouse::query()->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Second WH', 'code' => 'WH2',
        ]);
        $product = $this->stockedProduct($business->id, $warehouseA, $branch, $owner, 100);

        $this->actingAs($owner)->post('/inventory/stock-transfers', [
            'from_warehouse_id' => $warehouseA->id,
            'to_warehouse_id' => $warehouseB->id,
            'items' => [['product_id' => $product->id, 'quantity' => 30]],
        ])->assertSessionHasNoErrors();

        $transfer = StockTransfer::query()->where('business_id', $business->id)->first();

        $this->actingAs($owner)->post("/inventory/stock-transfers/{$transfer->id}/dispatch")
            ->assertSessionHasNoErrors();
        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $transfer->fresh()->status);
        $this->assertSame('70.000', Inventory::query()->where('warehouse_id', $warehouseA->id)->where('product_id', $product->id)->first()->quantity);

        $this->actingAs($owner)->post("/inventory/stock-transfers/{$transfer->id}/receive")
            ->assertSessionHasNoErrors();
        $this->assertSame(StockTransfer::STATUS_COMPLETED, $transfer->fresh()->status);
        $this->assertSame('30.000', Inventory::query()->where('warehouse_id', $warehouseB->id)->where('product_id', $product->id)->first()->quantity);
    }

    public function test_pending_transfer_can_be_cancelled_but_dispatched_one_cannot(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = $business->branches->first();
        $warehouseA = $business->warehouses->first();
        $warehouseB = Warehouse::query()->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Second WH', 'code' => 'WH2',
        ]);
        $product = $this->stockedProduct($business->id, $warehouseA, $branch, $owner, 50);

        $transferService = app(StockTransferService::class);
        $transfer = $transferService->create($business->id, $owner, [
            'from_warehouse_id' => $warehouseA->id, 'to_warehouse_id' => $warehouseB->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ]);

        $this->actingAs($owner)->post("/inventory/stock-transfers/{$transfer->id}/cancel")
            ->assertSessionHasNoErrors();
        $this->assertSame(StockTransfer::STATUS_CANCELLED, $transfer->fresh()->status);

        $transfer2 = $transferService->create($business->id, $owner, [
            'from_warehouse_id' => $warehouseA->id, 'to_warehouse_id' => $warehouseB->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ]);
        $transferService->dispatch($transfer2, $owner);

        $this->actingAs($owner)->post("/inventory/stock-transfers/{$transfer2->id}/cancel")
            ->assertSessionHasErrors('transfer');
    }

    public function test_inventory_count_generates_correction_movement_for_variance(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->stockedProduct($business->id, $warehouse, $branch, $owner, 100);

        $countService = app(InventoryCountService::class);
        $count = $countService->start($business->id, $warehouse->id, $owner);
        $item = $count->items->first();

        $this->actingAs($owner)->patch("/inventory/counts/items/{$item->id}", [
            'counted_quantity' => 92,
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post("/inventory/counts/{$count->id}/complete")
            ->assertSessionHasNoErrors();

        $this->assertSame(InventoryCount::STATUS_COMPLETED, $count->fresh()->status);
        $inventory = Inventory::query()->where('product_id', $product->id)->first();
        $this->assertSame('92.000', $inventory->quantity);
    }

    private function stockedProduct(string $businessId, $warehouse, $branch, $owner, int $quantity): Product
    {
        $product = app(ProductService::class)->create($businessId, [
            'name' => 'Stocked Product '.fake()->unique()->numerify('###'),
            'sku' => null, 'product_type' => 'simple', 'cost_price' => 10, 'selling_price' => 20,
        ]);

        app(StockMovementService::class)->record([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => 'opening', 'direction' => 'in',
            'quantity' => $quantity, 'created_by' => $owner->id,
        ]);

        return $product;
    }
}
