<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Accounting\Services\ExpenseService;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockMovementService;
use App\Domain\Purchasing\Events\GoodsReceived;
use App\Domain\Purchasing\Exceptions\GoodsReceivedException;
use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recording a delivery is the one place the Purchase Order lifecycle
 * meets real inventory and accounting effects: every good unit received
 * goes through StockMovementService (the platform's single entry point
 * for stock changes — it updates Inventory.quantity, recalculates the
 * weighted average cost, and writes the immutable StockMovement ledger
 * row), and the full processed value (good + damaged + rejected, since
 * that's what's actually owed to the supplier) becomes a real Accounting
 * Expense, not a separate fabricated "purchase journal".
 */
class GoodsReceivedService
{
    private const RECEIVABLE_STATUSES = [
        PurchaseOrder::STATUS_APPROVED,
        PurchaseOrder::STATUS_SENT,
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
    ];

    public function __construct(
        private readonly StockMovementService $stockMovements,
        private readonly ExpenseService $expenseService,
    ) {}

    /**
     * @param  array{
     *     business_id: string,
     *     purchase_order_id: string,
     *     branch_id: string,
     *     warehouse_id: string,
     *     received_by?: ?string,
     *     received_at?: ?string,
     *     notes?: ?string,
     *     items: array<int, array{
     *         purchase_order_item_id: string,
     *         quantity_received?: float|string|null,
     *         quantity_damaged?: float|string|null,
     *         quantity_rejected?: float|string|null,
     *         batch_number?: ?string,
     *         manufactured_date?: ?string,
     *         expiry_date?: ?string,
     *         notes?: ?string,
     *     }>,
     * }  $data
     *
     * @throws GoodsReceivedException
     */
    public function create(array $data): GoodsReceivedNote
    {
        return DB::transaction(function () use ($data) {
            $purchaseOrder = PurchaseOrder::query()
                ->where('id', $data['purchase_order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($purchaseOrder->status, self::RECEIVABLE_STATUSES, true)) {
                throw GoodsReceivedException::purchaseOrderNotReceivable($purchaseOrder->status);
            }

            $grn = GoodsReceivedNote::create([
                'business_id' => $data['business_id'],
                'purchase_order_id' => $purchaseOrder->id,
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'grn_number' => $this->generateGrnNumber($data['business_id']),
                'received_by' => $data['received_by'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['received_by'] ?? null,
            ]);

            $expenseAmount = '0.00';

            foreach ($data['items'] as $itemData) {
                $expenseAmount = bcadd($expenseAmount, $this->receiveItem($grn, $purchaseOrder, $itemData, $data), 2);
            }

            $this->recomputePurchaseOrderStatus($purchaseOrder);

            if (bccomp($expenseAmount, '0', 2) > 0) {
                $this->recordExpense($purchaseOrder, $grn, $expenseAmount);
            }

            GoodsReceived::dispatch($grn->load('items'));

            return $grn;
        });
    }

    /**
     * @param  array<string, mixed>  $itemData
     * @param  array<string, mixed>  $context
     *
     * @return string the line's processed value (good + damaged + rejected) * unit_cost, for the accounting entry.
     */
    private function receiveItem(GoodsReceivedNote $grn, PurchaseOrder $purchaseOrder, array $itemData, array $context): string
    {
        $purchaseOrderItem = PurchaseOrderItem::query()
            ->where('id', $itemData['purchase_order_item_id'])
            ->where('purchase_order_id', $purchaseOrder->id)
            ->first();

        if (! $purchaseOrderItem) {
            throw GoodsReceivedException::itemNotOnOrder();
        }

        $received = (string) ($itemData['quantity_received'] ?? 0);
        $damaged = (string) ($itemData['quantity_damaged'] ?? 0);
        $rejected = (string) ($itemData['quantity_rejected'] ?? 0);
        $totalProcessed = bcadd(bcadd($received, $damaged, 3), $rejected, 3);

        $remaining = $purchaseOrderItem->remainingQuantity();

        if (bccomp($totalProcessed, $remaining, 3) > 0) {
            throw GoodsReceivedException::overDelivery($purchaseOrderItem->product_name, $remaining, $totalProcessed);
        }

        $grn->items()->create([
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $purchaseOrderItem->product_id,
            'product_variant_id' => $purchaseOrderItem->product_variant_id,
            'quantity_received' => $received,
            'quantity_damaged' => $damaged,
            'quantity_rejected' => $rejected,
            'batch_number' => $itemData['batch_number'] ?? null,
            'manufactured_date' => $itemData['manufactured_date'] ?? null,
            'expiry_date' => $itemData['expiry_date'] ?? null,
            'notes' => $itemData['notes'] ?? null,
        ]);

        if (bccomp($received, '0', 3) > 0) {
            $batch = $this->resolveBatch($purchaseOrderItem, $itemData, $context, $received);

            $this->stockMovements->record([
                'business_id' => $context['business_id'],
                'branch_id' => $grn->branch_id,
                'warehouse_id' => $context['warehouse_id'],
                'product_id' => $purchaseOrderItem->product_id,
                'product_variant_id' => $purchaseOrderItem->product_variant_id,
                'product_batch_id' => $batch?->id,
                'type' => StockMovement::TYPE_PURCHASE,
                'direction' => StockMovement::DIRECTION_IN,
                'quantity' => $received,
                'unit_cost' => $purchaseOrderItem->unit_cost,
                'reference' => $grn,
                'notes' => "Goods received against {$purchaseOrder->po_number}",
                'created_by' => $context['received_by'] ?? null,
            ]);
        }

        $purchaseOrderItem->update([
            'quantity_received' => bcadd((string) $purchaseOrderItem->quantity_received, $totalProcessed, 3),
        ]);

        return bcmul($totalProcessed, (string) $purchaseOrderItem->unit_cost, 2);
    }

    /**
     * @param  array<string, mixed>  $itemData
     * @param  array<string, mixed>  $context
     */
    private function resolveBatch(
        PurchaseOrderItem $purchaseOrderItem,
        array $itemData,
        array $context,
        string $receivedQuantity,
    ): ?ProductBatch {
        if (empty($itemData['batch_number'])) {
            return null;
        }

        return ProductBatch::create([
            'business_id' => $context['business_id'],
            'product_id' => $purchaseOrderItem->product_id,
            'product_variant_id' => $purchaseOrderItem->product_variant_id,
            'warehouse_id' => $context['warehouse_id'],
            'batch_number' => $itemData['batch_number'],
            'manufactured_date' => $itemData['manufactured_date'] ?? null,
            'expiry_date' => $itemData['expiry_date'] ?? null,
            'quantity' => $receivedQuantity,
            'cost_price' => $purchaseOrderItem->unit_cost,
            'status' => ProductBatch::STATUS_ACTIVE,
        ]);
    }

    private function recomputePurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->load('items');

        $purchaseOrder->update([
            'status' => $purchaseOrder->isFullyReceived()
                ? PurchaseOrder::STATUS_FULLY_RECEIVED
                : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        ]);
    }

    private function recordExpense(PurchaseOrder $purchaseOrder, GoodsReceivedNote $grn, string $amount): void
    {
        $category = ExpenseCategory::query()->firstOrCreate(
            ['business_id' => $purchaseOrder->business_id, 'slug' => 'inventory-purchases'],
            ['name' => 'Inventory Purchases', 'is_active' => true],
        );

        $this->expenseService->create([
            'business_id' => $purchaseOrder->business_id,
            'branch_id' => $grn->branch_id,
            'expense_category_id' => $category->id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'title' => "Goods received — {$purchaseOrder->po_number}",
            'description' => "Auto-recorded from goods received note {$grn->grn_number}.",
            'amount' => $amount,
            'expense_date' => $grn->received_at->toDateString(),
            'payment_method' => 'other',
            'status' => Expense::STATUS_APPROVED,
        ]);
    }

    private function generateGrnNumber(string $businessId): string
    {
        do {
            $candidate = 'GRN-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (GoodsReceivedNote::query()->withTrashed()->where('business_id', $businessId)->where('grn_number', $candidate)->exists());

        return $candidate;
    }
}
