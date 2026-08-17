<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\InventoryCountItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryCountItem
 */
class InventoryCountItemResource extends JsonResource
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
            'expected_quantity' => $this->expected_quantity,
            'counted_quantity' => $this->counted_quantity,
            'variance' => $this->variance,
            'notes' => $this->notes,
        ];
    }
}
