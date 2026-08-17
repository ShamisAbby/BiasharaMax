<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Events\StockTransferCompleted;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * @param  array{from_warehouse_id: string, to_warehouse_id: string, notes?: ?string, items: array<int, array<string, mixed>>}  $data
     */
    public function create(string $businessId, User $createdBy, array $data): StockTransfer
    {
        return DB::transaction(function () use ($businessId, $createdBy, $data) {
            $transfer = StockTransfer::create([
                'business_id' => $businessId,
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'transfer_number' => $this->generateNumber($businessId),
                'status' => StockTransfer::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy->id,
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'product_batch_id' => $item['product_batch_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? null,
                ]);
            }

            return $transfer->load('items');
        });
    }

    /**
     * Deducts stock from the source warehouse. Stock is "in transit" from
     * this point — it has left the source but has not yet arrived at the
     * destination until receive() is called.
     */
    public function dispatch(StockTransfer $transfer, User $dispatchedBy): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $dispatchedBy) {
            $fromBranchId = Warehouse::query()->findOrFail($transfer->from_warehouse_id)->branch_id;

            foreach ($transfer->items as $item) {
                $this->stockMovementService->record([
                    'business_id' => $transfer->business_id,
                    'branch_id' => $fromBranchId,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_batch_id' => $item->product_batch_id,
                    'type' => StockMovement::TYPE_TRANSFER_OUT,
                    'direction' => StockMovement::DIRECTION_OUT,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'reference' => $transfer,
                    'created_by' => $dispatchedBy->id,
                ]);
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_IN_TRANSIT,
                'dispatched_by' => $dispatchedBy->id,
                'dispatched_at' => now(),
            ]);

            return $transfer;
        });
    }

    /**
     * Adds stock to the destination warehouse and completes the transfer.
     */
    public function receive(StockTransfer $transfer, User $receivedBy): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $receivedBy) {
            $toBranchId = Warehouse::query()->findOrFail($transfer->to_warehouse_id)->branch_id;

            foreach ($transfer->items as $item) {
                $this->stockMovementService->record([
                    'business_id' => $transfer->business_id,
                    'branch_id' => $toBranchId,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_batch_id' => $item->product_batch_id,
                    'type' => StockMovement::TYPE_TRANSFER_IN,
                    'direction' => StockMovement::DIRECTION_IN,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'reference' => $transfer,
                    'created_by' => $receivedBy->id,
                ]);
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_COMPLETED,
                'received_by' => $receivedBy->id,
                'received_at' => now(),
            ]);

            StockTransferCompleted::dispatch($transfer);

            return $transfer;
        });
    }

    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== StockTransfer::STATUS_PENDING) {
            throw new \LogicException('Only pending transfers can be cancelled; this one has already been dispatched.');
        }

        $transfer->update(['status' => StockTransfer::STATUS_CANCELLED]);

        return $transfer;
    }

    private function generateNumber(string $businessId): string
    {
        $count = StockTransfer::query()->where('business_id', $businessId)->withTrashed()->count() + 1;

        return 'TRF-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
