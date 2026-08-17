<?php

namespace App\Domain\Purchasing\Http\Resources;

use App\Domain\Purchasing\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderItem
 */
class PurchaseOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_received' => $this->quantity_received,
            'remaining_quantity' => $this->remainingQuantity(),
            'unit_cost' => $this->unit_cost,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'notes' => $this->notes,
        ];
    }
}
