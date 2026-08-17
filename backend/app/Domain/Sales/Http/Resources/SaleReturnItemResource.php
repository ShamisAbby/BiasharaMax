<?php

namespace App\Domain\Sales\Http\Resources;

use App\Domain\Sales\Models\SaleReturnItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleReturnItem
 */
class SaleReturnItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_item_id' => $this->sale_item_id,
            'product' => $this->whenLoaded('product', fn () => $this->product ? ['id' => $this->product->id, 'name' => $this->product->name] : null),
            'quantity_returned' => $this->quantity_returned,
            'condition' => $this->condition,
            'restock' => $this->restock,
            'unit_price' => $this->unit_price,
            'line_refund_amount' => $this->line_refund_amount,
            'notes' => $this->notes,
        ];
    }
}
