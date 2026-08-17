<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Events\LowStockDetected;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single entry point for every stock-quantity change in the platform.
 * No other code should mutate `inventories.quantity` directly — going
 * through here guarantees the live stock row and the immutable
 * stock_movements ledger never drift apart, and that negative-stock
 * control is enforced consistently regardless of which module (Inventory,
 * future Sales, future Purchasing) triggered the change.
 */
class StockMovementService
{
    /**
     * @param  array{
     *     business_id: string,
     *     branch_id: string,
     *     warehouse_id: string,
     *     product_id: string,
     *     product_variant_id?: ?string,
     *     product_batch_id?: ?string,
     *     warehouse_location_id?: ?string,
     *     type: string,
     *     direction: string,
     *     quantity: float|string,
     *     unit_cost?: float|string|null,
     *     reference?: ?Model,
     *     notes?: ?string,
     *     created_by?: ?string,
     * }  $data
     *
     * @throws InsufficientStockException
     */
    public function record(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $inventory = $this->lockOrCreateInventoryRow($data);

            $quantityBefore = (string) $inventory->quantity;
            $delta = (string) $data['quantity'];
            $quantityAfter = $data['direction'] === StockMovement::DIRECTION_IN
                ? bcadd($quantityBefore, $delta, 3)
                : bcsub($quantityBefore, $delta, 3);

            if (bccomp($quantityAfter, '0', 3) < 0 && ! $this->negativeStockAllowed($data['business_id'])) {
                $product = Product::query()->findOrFail($data['product_id']);

                throw InsufficientStockException::forProduct($product->name, $quantityBefore, $delta);
            }

            $unitCost = $data['unit_cost'] ?? null;

            $inventory->quantity = $quantityAfter;

            if ($data['direction'] === StockMovement::DIRECTION_IN && $unitCost !== null) {
                $inventory->average_cost = $this->recalculateAverageCost(
                    $quantityBefore,
                    (string) $inventory->average_cost,
                    $delta,
                    (string) $unitCost,
                );
            }

            $inventory->save();

            $movement = StockMovement::create([
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'product_id' => $data['product_id'],
                'product_variant_id' => $data['product_variant_id'] ?? null,
                'product_batch_id' => $data['product_batch_id'] ?? null,
                'type' => $data['type'],
                'direction' => $data['direction'],
                'quantity' => $delta,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost !== null ? bcmul($delta, (string) $unitCost, 2) : null,
                'reference_type' => ($data['reference'] ?? null)?->getMorphClass(),
                'reference_id' => ($data['reference'] ?? null)?->getKey(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            if ($data['type'] === StockMovement::TYPE_PURCHASE && $unitCost !== null) {
                // Deliberately an instance update, not
                // Product::query()->where(...)->update([...]) — a query
                // builder mass update bypasses Eloquent model events
                // entirely, which means Product's SyncsMoneyMinorColumns
                // trait (hooked on the `saving` event) would never fire and
                // last_purchase_price_minor would silently go stale.
                Product::query()->where('id', $data['product_id'])->first()?->update([
                    'last_purchase_price' => $unitCost,
                    'last_purchase_at' => now(),
                ]);
            }

            if ($inventory->isLowStock()) {
                LowStockDetected::dispatch($inventory);
            }

            return $movement;
        });
    }

    private function lockOrCreateInventoryRow(array $data): Inventory
    {
        $query = Inventory::query()
            ->where('warehouse_id', $data['warehouse_id'])
            ->where('product_id', $data['product_id']);

        if (! empty($data['product_variant_id'])) {
            $query->where('product_variant_id', $data['product_variant_id']);
        } else {
            $query->whereNull('product_variant_id');
        }

        $inventory = $query->lockForUpdate()->first();

        if ($inventory !== null) {
            return $inventory;
        }

        $defaults = $this->defaultStockLevels($data['product_id'], $data['product_variant_id'] ?? null);

        return Inventory::create([
            'business_id' => $data['business_id'],
            'branch_id' => $data['branch_id'],
            'warehouse_id' => $data['warehouse_id'],
            'warehouse_location_id' => $data['warehouse_location_id'] ?? null,
            'product_id' => $data['product_id'],
            'product_variant_id' => $data['product_variant_id'] ?? null,
            'quantity' => 0,
            ...$defaults,
        ]);
    }

    /**
     * @return array{minimum_stock: mixed, maximum_stock: mixed, reorder_level: mixed}
     */
    private function defaultStockLevels(string $productId, ?string $productVariantId): array
    {
        $product = Product::query()->find($productId);

        return [
            'minimum_stock' => $product?->minimum_stock,
            'maximum_stock' => $product?->maximum_stock,
            'reorder_level' => $product?->reorder_level,
        ];
    }

    private function recalculateAverageCost(
        string $quantityBefore,
        string $averageCostBefore,
        string $quantityIn,
        string $unitCostIn,
    ): string {
        $existingValue = bcmul($quantityBefore, $averageCostBefore, 4);
        $incomingValue = bcmul($quantityIn, $unitCostIn, 4);
        $totalQuantity = bcadd($quantityBefore, $quantityIn, 3);

        if (bccomp($totalQuantity, '0', 3) <= 0) {
            return $unitCostIn;
        }

        return bcdiv(bcadd($existingValue, $incomingValue, 4), $totalQuantity, 4);
    }

    private function negativeStockAllowed(string $businessId): bool
    {
        $business = Business::query()->find($businessId);

        return (bool) ($business?->settings['allow_negative_stock'] ?? false);
    }
}
