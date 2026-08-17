<?php

namespace App\Domain\Purchasing\Http\Resources;

use App\Domain\Purchasing\Models\GoodsReceivedItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceivedItem
 */
class GoodsReceivedItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'product' => $this->whenLoaded('product', fn () => $this->product ? ['id' => $this->product->id, 'name' => $this->product->name] : null),
            'quantity_received' => $this->quantity_received,
            'quantity_damaged' => $this->quantity_damaged,
            'quantity_rejected' => $this->quantity_rejected,
            'batch_number' => $this->batch_number,
            'manufactured_date' => $this->manufactured_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
