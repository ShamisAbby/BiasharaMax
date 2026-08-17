<?php

namespace Tests\Feature\Inventory;

use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Pivots\ProductSupplier;
use App\Domain\Inventory\Models\StockAdjustmentItem;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTransferItem;
use App\Domain\Inventory\Services\ProductService;
use App\Domain\Inventory\Services\StockAdjustmentService;
use App\Domain\Inventory\Services\StockMovementService;
use App\Domain\Inventory\Services\StockTransferService;
use App\Domain\Purchasing\Models\Supplier;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Proves docs/ADR/0002-money-format-migration.md's dual-write invariant for
 * the Inventory context (fourth of six) — the one with two different
 * scales: standard `_minor` (Product, ProductVariant, ProductBatch,
 * ProductSupplier pivot, StockMovement.total_cost) and the special
 * `_micros` scale (StockMovement/StockAdjustmentItem/StockTransferItem
 * .unit_cost, Inventory.average_cost — see
 * App\Domain\Shared\Concerns\SyncsMoneyMicroColumns).
 *
 * Like Purchasing, none of these services' own arithmetic needed
 * rewriting — StockMovementService already computes correct decimal(x,4)/
 * decimal(x,2) values via bcmath, so listing every column in
 * moneyMinorColumns()/moneyMicroColumns() is sufficient.
 */
class MoneyMinorSyncTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function assertDecimalMinorAgree(string $decimal, int $minor, string $label): void
    {
        $this->assertSame(
            $decimal,
            bcdiv((string) $minor, '100', 2),
            "{$label} decimal/_minor mismatch: {$decimal} vs {$minor}"
        );
    }

    private function assertDecimalMicrosAgree(string $decimal, int $micros, string $label): void
    {
        $this->assertSame(
            $decimal,
            bcdiv((string) $micros, '1000000', 4),
            "{$label} decimal/_micros mismatch: {$decimal} vs {$micros}"
        );
    }

    public function test_product_created_with_only_decimal_prices_derives_minor_on_every_field(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $product = app(ProductService::class)->create($business->id, [
            'name' => 'Widget',
            'sku' => null,
            'product_type' => 'simple',
            'cost_price' => '600.00',
            'purchase_price' => '650.00',
            'selling_price' => '1000.00',
            'wholesale_price' => '900.00',
            'minimum_price' => '700.00',
        ]);

        foreach (['cost_price', 'purchase_price', 'selling_price', 'wholesale_price', 'minimum_price'] as $field) {
            $this->assertDecimalMinorAgree((string) $product->{$field}, $product->{"{$field}_minor"}, "product.{$field}");
        }

        // Product::duplicate() uses replicate(), a different code path from
        // create() — proves that path stays consistent too.
        $copy = app(ProductService::class)->duplicate($product);
        $this->assertSame($product->cost_price_minor, $copy->cost_price_minor);
        $this->assertDecimalMinorAgree((string) $copy->selling_price, $copy->selling_price_minor, 'duplicated product.selling_price');
    }

    public function test_recording_an_inbound_purchase_derives_micros_and_minor_across_the_chain(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = app(ProductService::class)->create($business->id, [
            'name' => 'Widget', 'sku' => null, 'product_type' => 'simple',
            'cost_price' => 100, 'selling_price' => 150,
        ]);

        $movement = app(StockMovementService::class)->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_PURCHASE, 'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 10, 'unit_cost' => '123.4567', 'created_by' => $owner->id,
        ]);

        $this->assertDecimalMicrosAgree((string) $movement->unit_cost, $movement->unit_cost_micros, 'movement.unit_cost');
        $this->assertDecimalMinorAgree((string) $movement->total_cost, $movement->total_cost_minor, 'movement.total_cost');

        $inventory = Inventory::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertDecimalMicrosAgree((string) $inventory->average_cost, $inventory->average_cost_micros, 'inventory.average_cost');

        // The TYPE_PURCHASE branch in StockMovementService updates
        // Product.last_purchase_price via Product::query()->where(...)->first()?->update(...)
        // rather than a query-builder mass update specifically so this
        // fires model events and last_purchase_price_minor doesn't go stale.
        $product->refresh();
        $this->assertSame('123.46', $product->last_purchase_price);
        $this->assertDecimalMinorAgree((string) $product->last_purchase_price, $product->last_purchase_price_minor, 'product.last_purchase_price');
    }

    public function test_stock_adjustment_completion_derives_item_micros(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouse = $business->warehouses->first();
        $branch = $business->branches->first();
        $product = app(ProductService::class)->create($business->id, [
            'name' => 'Widget', 'sku' => null, 'product_type' => 'simple',
            'cost_price' => 100, 'selling_price' => 150,
        ]);
        app(StockMovementService::class)->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_OPENING, 'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 50, 'created_by' => $owner->id,
        ]);

        $adjustment = app(StockAdjustmentService::class)->create($business->id, $owner, [
            'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'reason' => 'correction',
            'items' => [['product_id' => $product->id, 'direction' => 'in', 'quantity' => 5, 'unit_cost' => '99.9999']],
        ]);
        app(StockAdjustmentService::class)->complete($adjustment, $owner);

        $item = StockAdjustmentItem::query()->where('stock_adjustment_id', $adjustment->id)->firstOrFail();
        $this->assertDecimalMicrosAgree((string) $item->unit_cost, $item->unit_cost_micros, 'stock_adjustment_item.unit_cost');
    }

    public function test_stock_transfer_dispatch_and_receive_derive_item_micros(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $warehouseA = $business->warehouses->first();
        $branch = $business->branches->first();
        $warehouseB = Warehouse::query()->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Second WH', 'code' => 'WH2',
        ]);
        $product = app(ProductService::class)->create($business->id, [
            'name' => 'Widget', 'sku' => null, 'product_type' => 'simple',
            'cost_price' => 100, 'selling_price' => 150,
        ]);
        app(StockMovementService::class)->record([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouseA->id,
            'product_id' => $product->id, 'type' => StockMovement::TYPE_OPENING, 'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 50, 'created_by' => $owner->id,
        ]);

        $transfer = app(StockTransferService::class)->create($business->id, $owner, [
            'from_warehouse_id' => $warehouseA->id, 'to_warehouse_id' => $warehouseB->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => '55.5555']],
        ]);
        app(StockTransferService::class)->dispatch($transfer, $owner);
        app(StockTransferService::class)->receive($transfer->fresh(), $owner);

        $item = StockTransferItem::query()->where('stock_transfer_id', $transfer->id)->firstOrFail();
        $this->assertDecimalMicrosAgree((string) $item->unit_cost, $item->unit_cost_micros, 'stock_transfer_item.unit_cost');
    }

    public function test_product_supplier_pivot_created_with_only_decimal_cost_derives_minor(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $product = app(ProductService::class)->create($business->id, [
            'name' => 'Widget', 'sku' => null, 'product_type' => 'simple',
            'cost_price' => 100, 'selling_price' => 150,
        ]);
        $supplier = Supplier::create(['business_id' => $business->id, 'name' => 'Acme', 'status' => Supplier::STATUS_ACTIVE]);

        $pivot = ProductSupplier::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'ACME-1',
            'supplier_cost_price' => '88.00',
        ]);

        $this->assertSame(8800, $pivot->supplier_cost_price_minor);
        $this->assertDecimalMinorAgree((string) $pivot->supplier_cost_price, $pivot->supplier_cost_price_minor, 'product_supplier.supplier_cost_price');
    }
}
