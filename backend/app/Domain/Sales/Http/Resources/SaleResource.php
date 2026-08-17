<?php

namespace App\Domain\Sales\Http\Resources;

use App\Domain\Sales\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'source' => $this->source,
            'delivery_address' => $this->delivery_address,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_due' => $this->balance_due,
            'notes' => $this->notes,
            'voided_at' => $this->voided_at,
            'void_reason' => $this->void_reason,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'sold_by' => $this->whenLoaded('soldBy', fn () => $this->soldBy?->name),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at,
        ];
    }
}
