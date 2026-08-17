<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductVariant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * One stock row per product per warehouse.
 *
 * This had no test, and the way it is enforced has now changed twice, so
 * it needs one. The original design put the key in a generated column;
 * MariaDB refuses both to store such a column (error 1901) and to index it
 * as virtual, so the key is now an ordinary column populated by a hook on
 * the model.
 *
 * That shift matters: the guarantee used to be entirely the database's,
 * and now depends on `Inventory::booted` running. If someone removes that
 * hook, or writes stock with a raw insert, the unique index silently stops
 * matching reality — every row gets `variant_key = ''` or NULL and either
 * everything collides or nothing does. These tests fail loudly in that
 * case, which is the only reason they exist.
 */
class InventoryUniquenessTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: Product}
     */
    private function scene(): array
    {
        [, $business] = $this->createOwnerWithBusiness();

        $product = Product::query()->create([
            'business_id' => $business->id,
            'name' => 'Sugar 1kg',
            'slug' => 'sugar-1kg-'.fake()->unique()->numerify('####'),
            'sku' => 'SKU-'.fake()->unique()->numerify('####'),
            'product_type' => 'simple',
            'status' => Product::STATUS_ACTIVE,
            'cost_price' => 1000,
            'selling_price' => 1500,
        ]);

        return [
            $business->id,
            $business->branches->first()->id,
            $business->warehouses->first()->id,
            $product,
        ];
    }

    private function row(string $businessId, string $branchId, string $warehouseId, Product $product, ?string $variantId = null): Inventory
    {
        return Inventory::query()->create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'quantity' => 0,
        ]);
    }

    public function test_a_simple_product_cannot_have_two_stock_rows_in_one_warehouse(): void
    {
        [$businessId, $branchId, $warehouseId, $product] = $this->scene();

        $this->row($businessId, $branchId, $warehouseId, $product);

        // The case the whole design exists for. With `product_variant_id`
        // left NULL and no `variant_key`, every engine treats the two rows
        // as distinct and this second insert succeeds — which is how a
        // shop ends up with its stock split across two rows and a till
        // that reports half of it.
        $this->expectException(QueryException::class);

        $this->row($businessId, $branchId, $warehouseId, $product);
    }

    public function test_the_same_product_may_be_stocked_in_two_warehouses(): void
    {
        [$businessId, $branchId, $warehouseId, $product] = $this->scene();

        $second = \App\Domain\Business\Models\Warehouse::query()->create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'name' => 'Second Store',
            'code' => 'W2',
        ]);

        $this->row($businessId, $branchId, $warehouseId, $product);
        $this->row($businessId, $branchId, $second->id, $product);

        $this->assertSame(2, Inventory::query()->where('product_id', $product->id)->count());
    }

    public function test_two_variants_of_one_product_each_get_their_own_row(): void
    {
        [$businessId, $branchId, $warehouseId, $product] = $this->scene();

        $variants = collect(['S', 'M'])->map(fn (string $size): ProductVariant => ProductVariant::query()->create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'attributes' => ['size' => $size],
            'cost_price' => 1000,
            'selling_price' => 1500,
        ]));

        foreach ($variants as $variant) {
            $this->row($businessId, $branchId, $warehouseId, $product, $variant->id);
        }

        $this->assertSame(2, Inventory::query()->where('product_id', $product->id)->count());
    }

    public function test_one_variant_cannot_have_two_stock_rows_in_one_warehouse(): void
    {
        [$businessId, $branchId, $warehouseId, $product] = $this->scene();

        $variant = ProductVariant::query()->create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'attributes' => ['size' => 'Large'],
            'cost_price' => 1000,
            'selling_price' => 1500,
        ]);

        $this->row($businessId, $branchId, $warehouseId, $product, $variant->id);

        $this->expectException(QueryException::class);

        $this->row($businessId, $branchId, $warehouseId, $product, $variant->id);
    }

    /**
     * The hook, tested directly rather than only through its effect.
     *
     * If this passes but the collision tests fail, the index is wrong. If
     * this fails, the hook is — and the two failures point at very
     * different files.
     */
    public function test_the_variant_key_mirrors_the_variant_id(): void
    {
        [$businessId, $branchId, $warehouseId, $product] = $this->scene();

        $simple = $this->row($businessId, $branchId, $warehouseId, $product);

        // Empty string, never null. A null here would exempt the row from
        // the unique index and quietly undo the whole thing.
        $this->assertSame('', $simple->fresh()->variant_key);

        $variant = ProductVariant::query()->create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'attributes' => ['size' => 'Small'],
            'cost_price' => 1000,
            'selling_price' => 1500,
        ]);

        $withVariant = $this->row($businessId, $branchId, $warehouseId, $product, $variant->id);

        $this->assertSame($variant->id, $withVariant->fresh()->variant_key);
    }

    /**
     * Reassigning a row has to move the key with it.
     *
     * This is why the hook is on `saving` rather than `creating`. On
     * `creating` the key would freeze at its first value, and a row moved
     * from a variant to a simple product would keep the old variant's key
     * — constraining nothing and colliding with nothing.
     */
    public function test_changing_the_variant_updates_the_key(): void
    {
        [$businessId, $branchId, $warehouseId, $product] = $this->scene();

        $variant = ProductVariant::query()->create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'attributes' => ['size' => 'Small'],
            'cost_price' => 1000,
            'selling_price' => 1500,
        ]);

        $inventory = $this->row($businessId, $branchId, $warehouseId, $product, $variant->id);

        $inventory->update(['product_variant_id' => null]);

        $this->assertSame('', $inventory->fresh()->variant_key);
    }
}
