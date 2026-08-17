<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Events\PurchaseOrderApproved;
use App\Domain\Purchasing\Exceptions\PurchaseOrderException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single entry point for the Purchase Order lifecycle: draft ->
 * pending_approval -> approved/rejected -> sent -> partially_received ->
 * fully_received -> closed (or cancelled from most non-terminal states).
 * Receiving itself — and the resulting inventory/accounting effects — is
 * GoodsReceivedService's job, not this one.
 */
class PurchaseOrderService
{
    private const EDITABLE_STATUSES = [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING_APPROVAL];

    private const CANCELLABLE_STATUSES = [
        PurchaseOrder::STATUS_DRAFT,
        PurchaseOrder::STATUS_PENDING_APPROVAL,
        PurchaseOrder::STATUS_APPROVED,
        PurchaseOrder::STATUS_SENT,
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
    ];

    /**
     * @param  array{
     *     business_id: string,
     *     branch_id?: ?string,
     *     warehouse_id?: ?string,
     *     supplier_id: string,
     *     order_date: string,
     *     expected_delivery_date?: ?string,
     *     items: array<int, array{product_id: string, product_variant_id?: ?string, quantity_ordered: float|string, unit_cost: float|string, discount_amount?: float|string|null, tax_amount?: float|string|null, notes?: ?string}>,
     *     discount_amount?: float|string|null,
     *     shipping_cost?: float|string|null,
     *     other_charges?: float|string|null,
     *     notes?: ?string,
     *     terms?: ?string,
     *     created_by?: ?string,
     * }  $data
     *
     * @throws PurchaseOrderException
     */
    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            [$lineItems, $subtotal, $itemDiscount, $taxAmount] = $this->buildLineItems($data['items'] ?? []);

            $poDiscount = (string) ($data['discount_amount'] ?? 0);
            $shipping = (string) ($data['shipping_cost'] ?? 0);
            $other = (string) ($data['other_charges'] ?? 0);
            $totalDiscount = bcadd($itemDiscount, $poDiscount, 2);

            $totalAmount = bcadd(
                bcadd(bcsub(bcadd($subtotal, $taxAmount, 2), $totalDiscount, 2), $shipping, 2),
                $other,
                2,
            );

            $purchaseOrder = PurchaseOrder::create([
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'po_number' => $this->generatePoNumber($data['business_id']),
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $shipping,
                'other_charges' => $other,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($lineItems as $lineItem) {
                $purchaseOrder->items()->create($lineItem);
            }

            return $purchaseOrder->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchaseOrderException
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if (! in_array($purchaseOrder->status, self::EDITABLE_STATUSES, true)) {
            throw PurchaseOrderException::invalidTransition($purchaseOrder->status, 'edited');
        }

        return DB::transaction(function () use ($purchaseOrder, $data) {
            [$lineItems, $subtotal, $itemDiscount, $taxAmount] = $this->buildLineItems($data['items'] ?? []);

            $poDiscount = (string) ($data['discount_amount'] ?? 0);
            $shipping = (string) ($data['shipping_cost'] ?? 0);
            $other = (string) ($data['other_charges'] ?? 0);
            $totalDiscount = bcadd($itemDiscount, $poDiscount, 2);

            $totalAmount = bcadd(
                bcadd(bcsub(bcadd($subtotal, $taxAmount, 2), $totalDiscount, 2), $shipping, 2),
                $other,
                2,
            );

            $purchaseOrder->update([
                'branch_id' => $data['branch_id'] ?? $purchaseOrder->branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? $purchaseOrder->warehouse_id,
                'supplier_id' => $data['supplier_id'] ?? $purchaseOrder->supplier_id,
                'order_date' => $data['order_date'] ?? $purchaseOrder->order_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $purchaseOrder->expected_delivery_date,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $shipping,
                'other_charges' => $other,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? $purchaseOrder->notes,
                'terms' => $data['terms'] ?? $purchaseOrder->terms,
            ]);

            $purchaseOrder->items()->delete();
            foreach ($lineItems as $lineItem) {
                $purchaseOrder->items()->create($lineItem);
            }

            return $purchaseOrder->load('items');
        });
    }

    /**
     * @throws PurchaseOrderException
     */
    public function delete(PurchaseOrder $purchaseOrder): void
    {
        if (! in_array($purchaseOrder->status, self::EDITABLE_STATUSES, true)) {
            throw PurchaseOrderException::invalidTransition($purchaseOrder->status, 'deleted');
        }

        $purchaseOrder->delete();
    }

    public function duplicate(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $purchaseOrder->loadMissing('items');

        return $this->create([
            'business_id' => $purchaseOrder->business_id,
            'branch_id' => $purchaseOrder->branch_id,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => null,
            'items' => $purchaseOrder->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity_ordered' => $item->quantity_ordered,
                'unit_cost' => $item->unit_cost,
                'discount_amount' => $item->discount_amount,
                'tax_amount' => $item->tax_amount,
                'notes' => $item->notes,
            ])->all(),
            'discount_amount' => 0,
            'shipping_cost' => $purchaseOrder->shipping_cost,
            'other_charges' => $purchaseOrder->other_charges,
            'notes' => $purchaseOrder->notes,
            'terms' => $purchaseOrder->terms,
            'created_by' => $purchaseOrder->created_by,
        ]);
    }

    /**
     * @throws PurchaseOrderException
     */
    public function submitForApproval(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw PurchaseOrderException::invalidTransition($purchaseOrder->status, 'submitted for approval');
        }

        if ($purchaseOrder->items()->count() === 0) {
            throw PurchaseOrderException::noItems();
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_PENDING_APPROVAL]);

        return $purchaseOrder->refresh();
    }

    /**
     * @throws PurchaseOrderException
     */
    public function approve(PurchaseOrder $purchaseOrder, string $approvedBy): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw PurchaseOrderException::invalidTransition($purchaseOrder->status, 'approved');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        PurchaseOrderApproved::dispatch($purchaseOrder->refresh());

        return $purchaseOrder;
    }

    /**
     * @throws PurchaseOrderException
     */
    public function reject(PurchaseOrder $purchaseOrder, string $rejectedBy, string $reason): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw PurchaseOrderException::invalidTransition($purchaseOrder->status, 'rejected');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_REJECTED,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $purchaseOrder->refresh();
    }

    /**
     * @throws PurchaseOrderException
     */
    public function send(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_APPROVED) {
            throw PurchaseOrderException::invalidTransition($purchaseOrder->status, 'sent to the supplier');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_SENT,
            'sent_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }

    /**
     * @throws PurchaseOrderException
     */
    public function cancel(PurchaseOrder $purchaseOrder, string $reason): PurchaseOrder
    {
        if (! in_array($purchaseOrder->status, self::CANCELLABLE_STATUSES, true)) {
            throw PurchaseOrderException::invalidTransition($purchaseOrder->status, 'cancelled');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }

    /**
     * @throws PurchaseOrderException
     */
    public function close(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_FULLY_RECEIVED) {
            throw PurchaseOrderException::invalidTransition($purchaseOrder->status, 'closed');
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CLOSED, 'closed_at' => now()]);

        return $purchaseOrder->refresh();
    }

    /**
     * @param  array<int, array{product_id: string, product_variant_id?: ?string, quantity_ordered: float|string, unit_cost: float|string, discount_amount?: float|string|null, tax_amount?: float|string|null, notes?: ?string}>  $items
     * @return array{0: array<int, array<string, mixed>>, 1: string, 2: string, 3: string}
     */
    private function buildLineItems(array $items): array
    {
        $lineItems = [];
        $subtotal = '0.00';
        $totalDiscount = '0.00';
        $totalTax = '0.00';

        foreach ($items as $item) {
            $product = Product::query()->findOrFail($item['product_id']);
            $quantity = (string) $item['quantity_ordered'];
            $unitCost = (string) $item['unit_cost'];
            $discount = (string) ($item['discount_amount'] ?? 0);
            $tax = (string) ($item['tax_amount'] ?? 0);

            $lineSubtotal = bcmul($quantity, $unitCost, 2);
            $lineTotal = bcadd(bcsub($lineSubtotal, $discount, 2), $tax, 2);

            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            $totalDiscount = bcadd($totalDiscount, $discount, 2);
            $totalTax = bcadd($totalTax, $tax, 2);

            $lineItems[] = [
                'product_id' => $product->id,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity_ordered' => $quantity,
                'unit_cost' => $unitCost,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'line_total' => $lineTotal,
                'notes' => $item['notes'] ?? null,
            ];
        }

        return [$lineItems, $subtotal, $totalDiscount, $totalTax];
    }

    private function generatePoNumber(string $businessId): string
    {
        do {
            $candidate = 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (PurchaseOrder::query()->withTrashed()->where('business_id', $businessId)->where('po_number', $candidate)->exists());

        return $candidate;
    }
}
