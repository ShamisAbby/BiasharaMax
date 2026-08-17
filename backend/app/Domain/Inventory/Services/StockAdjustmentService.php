<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * @param  array{warehouse_id: string, branch_id: string, reason: string, notes?: ?string, items: array<int, array<string, mixed>>}  $data
     */
    public function create(string $businessId, User $createdBy, array $data): StockAdjustment
    {
        return DB::transaction(function () use ($businessId, $createdBy, $data) {
            $adjustment = StockAdjustment::create([
                'business_id' => $businessId,
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'adjustment_number' => $this->generateNumber($businessId),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => StockAdjustment::STATUS_DRAFT,
                'created_by' => $createdBy->id,
            ]);

            foreach ($data['items'] as $item) {
                $batchId = $item['product_batch_id'] ?? null;

                // For stock-in items carrying batch metadata, create the ProductBatch now
                // so expiry tracking is active immediately on completion.
                if (
                    ($item['direction'] ?? '') === 'in' &&
                    ! $batchId &&
                    (! empty($item['batch_number']) || ! empty($item['expiry_date']) || ! empty($item['manufactured_date']))
                ) {
                    $batch = ProductBatch::create([
                        'business_id'       => $businessId,
                        'product_id'        => $item['product_id'],
                        'product_variant_id'=> $item['product_variant_id'] ?? null,
                        'warehouse_id'      => $data['warehouse_id'],
                        'batch_number'      => $item['batch_number'] ?? null,
                        'manufactured_date' => $item['manufactured_date'] ?? null,
                        'expiry_date'       => $item['expiry_date'] ?? null,
                        'quantity'          => $item['quantity'],
                        'cost_price'        => $item['unit_cost'] ?? null,
                        'status'            => ProductBatch::STATUS_ACTIVE,
                    ]);
                    $batchId = $batch->id;
                }

                $adjustment->items()->create([
                    'product_id'         => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'product_batch_id'   => $batchId,
                    'direction'          => $item['direction'],
                    'quantity'           => $item['quantity'],
                    'unit_cost'          => $item['unit_cost'] ?? null,
                ]);
            }

            return $adjustment->load('items');
        });
    }

    /**
     * Applies every line item to stock via StockMovementService and marks
     * the adjustment completed. Draft adjustments have no stock effect at
     * all — only completing one moves inventory.
     */
    public function complete(StockAdjustment $adjustment, User $completedBy): StockAdjustment
    {
        if ($adjustment->isCompleted()) {
            return $adjustment;
        }

        return DB::transaction(function () use ($adjustment, $completedBy) {
            foreach ($adjustment->items as $item) {
                $this->stockMovementService->record([
                    'business_id' => $adjustment->business_id,
                    'branch_id' => $adjustment->branch_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_batch_id' => $item->product_batch_id,
                    'type' => $item->direction === StockMovement::DIRECTION_IN
                        ? StockMovement::TYPE_ADJUSTMENT_INCREASE
                        : $this->movementTypeForReason($adjustment->reason),
                    'direction' => $item->direction,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'reference' => $adjustment,
                    'notes' => $adjustment->notes,
                    'created_by' => $completedBy->id,
                ]);
            }

            $adjustment->update([
                'status' => StockAdjustment::STATUS_COMPLETED,
                'completed_by' => $completedBy->id,
                'completed_at' => now(),
            ]);

            return $adjustment;
        });
    }

    private function movementTypeForReason(string $reason): string
    {
        return match ($reason) {
            StockAdjustment::REASON_DAMAGE => StockMovement::TYPE_DAMAGE,
            StockAdjustment::REASON_THEFT => StockMovement::TYPE_THEFT,
            StockAdjustment::REASON_EXPIRY => StockMovement::TYPE_EXPIRY,
            default => StockMovement::TYPE_ADJUSTMENT_DECREASE,
        };
    }

    private function generateNumber(string $businessId): string
    {
        $count = StockAdjustment::query()->where('business_id', $businessId)->withTrashed()->count() + 1;

        return 'ADJ-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
