<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\InventoryCount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryCount
 */
class InventoryCountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'count_number' => $this->count_number,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse->name),
            'status' => $this->status,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'items' => InventoryCountItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
