<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
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
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse->name),
            'type' => $this->type,
            'direction' => $this->direction,
            'quantity' => $this->quantity,
            'quantity_before' => $this->quantity_before,
            'quantity_after' => $this->quantity_after,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
