<?php

namespace App\Domain\Purchasing\Http\Resources;

use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'order_date' => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'shipping_cost' => $this->shipping_cost,
            'other_charges' => $this->other_charges,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_due' => $this->balance_due,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'rejection_reason' => $this->rejection_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'sent_at' => $this->sent_at,
            'approved_at' => $this->approved_at,
            'cancelled_at' => $this->cancelled_at,
            'closed_at' => $this->closed_at,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'email' => $this->supplier->email,
                'phone' => $this->supplier->phone,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? ['id' => $this->approvedBy->id, 'name' => $this->approvedBy->name] : null),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'payments' => SupplierPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
        ];
    }
}
