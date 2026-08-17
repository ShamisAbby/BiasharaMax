<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\StockTransferItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransferItem
 */
class StockTransferItemResource extends JsonResource
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
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
        ];
    }
}
