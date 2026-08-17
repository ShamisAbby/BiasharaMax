<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\InventoryCountItem;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryCountService
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * Starts a physical count by snapshotting the current system quantity
     * for every product currently stocked in the warehouse. Counters fill
     * in `counted_quantity` afterward; the system never assumes it knows
     * what's physically on the shelf.
     */
    public function start(string $businessId, string $warehouseId, User $startedBy): InventoryCount
    {
        return DB::transaction(function () use ($businessId, $warehouseId, $startedBy) {
            $count = InventoryCount::create([
                'business_id' => $businessId,
                'warehouse_id' => $warehouseId,
                'count_number' => $this->generateNumber($businessId),
                'status' => InventoryCount::STATUS_IN_PROGRESS,
                'started_by' => $startedBy->id,
                'started_at' => now(),
            ]);

            Inventory::query()
                ->where('warehouse_id', $warehouseId)
                ->each(function (Inventory $inventory) use ($count) {
                    $count->items()->create([
                        'product_id' => $inventory->product_id,
                        'product_variant_id' => $inventory->product_variant_id,
                        'expected_quantity' => $inventory->quantity,
                    ]);
                });

            return $count->load('items');
        });
    }

    public function recordCount(InventoryCountItem $item, string $countedQuantity): InventoryCountItem
    {
        $item->update([
            'counted_quantity' => $countedQuantity,
            'variance' => bcsub($countedQuantity, (string) $item->expected_quantity, 3),
        ]);

        return $item;
    }

    /**
     * Generates a count_correction stock movement for every item with a
     * non-zero variance, then marks the count completed.
     */
    public function complete(InventoryCount $count, User $completedBy): InventoryCount
    {
        return DB::transaction(function () use ($count, $completedBy) {
            $branchId = Warehouse::query()->findOrFail($count->warehouse_id)->branch_id;

            foreach ($count->items as $item) {
                if ($item->counted_quantity === null) {
                    continue;
                }

                $variance = (string) $item->variance;

                if (bccomp($variance, '0', 3) === 0) {
                    continue;
                }

                $this->stockMovementService->record([
                    'business_id' => $count->business_id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $count->warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'type' => StockMovement::TYPE_COUNT_CORRECTION,
                    'direction' => bccomp($variance, '0', 3) > 0 ? StockMovement::DIRECTION_IN : StockMovement::DIRECTION_OUT,
                    'quantity' => ltrim($variance, '-'),
                    'reference' => $count,
                    'notes' => "Inventory count {$count->count_number} correction",
                    'created_by' => $completedBy->id,
                ]);
            }

            $count->update([
                'status' => InventoryCount::STATUS_COMPLETED,
                'completed_by' => $completedBy->id,
                'completed_at' => now(),
            ]);

            return $count;
        });
    }

    private function generateNumber(string $businessId): string
    {
        $countOfCounts = InventoryCount::query()->where('business_id', $businessId)->withTrashed()->count() + 1;

        return 'CNT-'.str_pad((string) $countOfCounts, 5, '0', STR_PAD_LEFT);
    }
}
