<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransfer
 */
class StockTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'from_warehouse_name' => $this->whenLoaded('fromWarehouse', fn () => $this->fromWarehouse->name),
            'to_warehouse_name' => $this->whenLoaded('toWarehouse', fn () => $this->toWarehouse->name),
            'status' => $this->status,
            'notes' => $this->notes,
            'dispatched_at' => $this->dispatched_at,
            'received_at' => $this->received_at,
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
