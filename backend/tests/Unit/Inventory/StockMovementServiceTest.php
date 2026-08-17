<?php

namespace Tests\Unit\Inventory;

use App\Domain\Inventory\Events\LowStockDetected;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\ProductService;
use App\Domain\Inventory\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private StockMovementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockMovementService::class);
    }

    public function test_recording_an_inbound_movement_increases_quantity_and_creates_inventory_row(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->createProduct($business->id);

        $movement = $this->service->record([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_OPENING,
            'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 50,
            'unit_cost' => 100,
            'created_by' => $owner->id,
        ]);

        $inventory = Inventory::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();

        $this->assertSame('50.000', $inventory->quantity);
        $this->assertSame('0.000', $movement->quantity_before);
        $this->assertSame('50.000', $movement->quantity_after);
        $this->assertSame('100.0000', $inventory->average_cost);
    }

    public function test_average_cost_is_recalculated_as_a_weighted_average_across_purchases(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->createProduct($business->id);

        // 10 units @ 100 = 1000
        $this->service->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_PURCHASE, 'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 10, 'unit_cost' => 100, 'created_by' => $owner->id,
        ]);

        // +10 units @ 200 = 2000. New average = (1000+2000)/20 = 150
        $this->service->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_PURCHASE, 'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 10, 'unit_cost' => 200, 'created_by' => $owner->id,
        ]);

        $inventory = Inventory::query()->where('product_id', $product->id)->first();

        $this->assertSame('20.000', $inventory->quantity);
        $this->assertSame('150.0000', $inventory->average_cost);
        $this->assertSame('200.00', $product->fresh()->last_purchase_price);
        $this->assertNotNull($product->fresh()->last_purchase_at);
    }

    public function test_outbound_movement_decreases_quantity(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->createProduct($business->id);

        $this->stock($product, $warehouse, $branch, $owner, 100);

        $this->service->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_SALE, 'direction' => StockMovement::DIRECTION_OUT,
            'quantity' => 30, 'created_by' => $owner->id,
        ]);

        $inventory = Inventory::query()->where('product_id', $product->id)->first();
        $this->assertSame('70.000', $inventory->quantity);
    }

    public function test_overselling_is_blocked_by_default(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->createProduct($business->id);

        $this->stock($product, $warehouse, $branch, $owner, 10);

        $this->expectException(InsufficientStockException::class);

        $this->service->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_SALE, 'direction' => StockMovement::DIRECTION_OUT,
            'quantity' => 50, 'created_by' => $owner->id,
        ]);
    }

    public function test_negative_stock_is_allowed_when_business_setting_enables_it(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->createProduct($business->id);

        $business->update(['settings' => ['allow_negative_stock' => true]]);

        $this->stock($product, $warehouse, $branch, $owner, 10);

        $this->service->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_SALE, 'direction' => StockMovement::DIRECTION_OUT,
            'quantity' => 50, 'created_by' => $owner->id,
        ]);

        $inventory = Inventory::query()->where('product_id', $product->id)->first();
        $this->assertSame('-40.000', $inventory->quantity);
    }

    public function test_low_stock_event_is_dispatched_when_quantity_drops_to_or_below_reorder_level(): void
    {
        Event::fake([LowStockDetected::class]);

        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->createProduct($business->id, ['reorder_level' => 10]);

        $this->stock($product, $warehouse, $branch, $owner, 15);

        $this->service->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_SALE, 'direction' => StockMovement::DIRECTION_OUT,
            'quantity' => 10, 'created_by' => $owner->id,
        ]);

        Event::assertDispatched(LowStockDetected::class);
    }

    public function test_stock_movements_are_immutable(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = $this->createProduct($business->id);

        $movement = $this->service->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_OPENING, 'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 10, 'created_by' => $owner->id,
        ]);

        $this->expectException(\LogicException::class);
        $movement->delete();
    }

    private function createProduct(string $businessId, array $overrides = []): Product
    {
        return app(ProductService::class)->create($businessId, [
            'name' => 'Test Product '.fake()->unique()->numerify('###'),
            'sku' => null,
            'product_type' => 'simple',
            'cost_price' => 100,
            'selling_price' => 150,
            ...$overrides,
        ]);
    }

    private function stock($product, $warehouse, $branch, $owner, int $quantity): void
    {
        $this->service->record([
            'business_id' => $product->business_id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_OPENING, 'direction' => StockMovement::DIRECTION_IN,
            'quantity' => $quantity, 'created_by' => $owner->id,
        ]);
    }
}
