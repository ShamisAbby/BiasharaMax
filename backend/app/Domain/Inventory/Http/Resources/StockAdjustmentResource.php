<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockAdjustment
 */
class StockAdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_number' => $this->adjustment_number,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse->name),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'status' => $this->status,
            'completed_at' => $this->completed_at,
            'items' => StockAdjustmentItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
