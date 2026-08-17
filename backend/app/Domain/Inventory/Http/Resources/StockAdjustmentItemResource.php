<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\StockAdjustmentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockAdjustmentItem
 */
class StockAdjustmentItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product->name),
            'direction' => $this->direction,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
        ];
    }
}
