<?php

namespace App\Domain\Sales\Listeners;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockMovementService;
use App\Domain\Sales\Events\SaleVoided;

/**
 * Synchronous for the same reason as DeductInventoryOnSaleCompletion: a
 * void must atomically restore stock within the same transaction.
 */
class RestoreInventoryOnSaleVoided
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function handle(SaleVoided $event): void
    {
        foreach ($event->sale->items as $item) {
            $this->stockMovementService->record([
                'business_id' => $event->sale->business_id,
                'branch_id' => $event->sale->branch_id,
                'warehouse_id' => $event->sale->warehouse_id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_batch_id' => $item->product_batch_id,
                'type' => StockMovement::TYPE_RETURN_IN,
                'direction' => StockMovement::DIRECTION_IN,
                'quantity' => $item->quantity,
                'reference' => $event->sale,
                'notes' => "Void of sale {$event->sale->sale_number}",
                'created_by' => $event->sale->voided_by,
            ]);
        }
    }
}
