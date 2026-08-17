<?php

namespace Tests\Unit\Inventory;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\InventoryDashboardService;
use App\Domain\Inventory\Services\ProductService;
use App\Domain\Inventory\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class InventoryDashboardServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_inventory_value_multiplies_quantity_by_average_cost(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();

        $product = app(ProductService::class)->create($business->id, [
            'name' => 'Widget',
            'sku' => null,
            'product_type' => 'simple',
            'cost_price' => 100,
            'selling_price' => 150,
        ]);

        app(StockMovementService::class)->record([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_OPENING,
            'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 10,
            'unit_cost' => 25,
            'created_by' => $owner->id,
        ]);

        $summary = app(InventoryDashboardService::class)->summary($business->id);

        $this->assertSame(250.0, $summary['inventory_value']);
    }

    public function test_health_score_is_100_when_there_are_no_products(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $summary = app(InventoryDashboardService::class)->summary($business->id);

        $this->assertSame(0, $summary['total_products']);
        $this->assertSame(100, $summary['health_score']);
    }
}
