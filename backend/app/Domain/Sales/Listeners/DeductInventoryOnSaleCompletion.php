<?php

namespace App\Domain\Sales\Listeners;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockMovementService;
use App\Domain\Sales\Events\SaleCompleted;

/**
 * Deliberately synchronous (no ShouldQueue) — stock must be deducted, and
 * insufficient-stock failures must surface, within the same request and
 * database transaction that creates the sale. A queued deduction could let
 * a sale succeed and only fail stock control moments later, which the
 * "stock cannot go below zero" business rule cannot tolerate.
 */
class DeductInventoryOnSaleCompletion
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        foreach ($event->sale->items as $item) {
            // A product with track_stock = false (or a service item) is
            // deliberately outside the inventory ledger — e.g. a repair
            // labor line, a made-to-order item, or a product this business
            // doesn't manage stock levels for. Deducting stock for it would
            // walk its inventory row permanently negative (it never
            // receives stock IN movements either) and every future sale of
            // it would fail with InsufficientStockException. Product
            // already exposes tracksStock() for exactly this check; nothing
            // else in the codebase was actually calling it.
            if (! $item->product?->tracksStock()) {
                continue;
            }

            $this->stockMovementService->record([
                'business_id' => $event->sale->business_id,
                'branch_id' => $event->sale->branch_id,
                'warehouse_id' => $event->sale->warehouse_id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_batch_id' => $item->product_batch_id,
                'type' => StockMovement::TYPE_SALE,
                'direction' => StockMovement::DIRECTION_OUT,
                'quantity' => $item->quantity,
                'reference' => $event->sale,
                'notes' => "Sale {$event->sale->sale_number}",
                'created_by' => $event->sale->sold_by,
            ]);
        }
    }
}
