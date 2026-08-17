<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inventory
 */
class InventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse->name),
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'reserved_quantity' => $this->reserved_quantity,
            'available_quantity' => $this->available_quantity,
            'minimum_stock' => $this->minimum_stock,
            'maximum_stock' => $this->maximum_stock,
            'reorder_level' => $this->reorder_level,
            'average_cost' => $this->average_cost,
            'is_low_stock' => $this->isLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'last_counted_at' => $this->last_counted_at,
        ];
    }
}
