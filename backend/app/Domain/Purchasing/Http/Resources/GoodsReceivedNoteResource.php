<?php

namespace App\Domain\Purchasing\Http\Resources;

use App\Domain\Purchasing\Models\GoodsReceivedNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceivedNote
 */
class GoodsReceivedNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grn_number' => $this->grn_number,
            'received_at' => $this->received_at,
            'notes' => $this->notes,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder ? [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'status' => $this->purchaseOrder->status,
            ] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy ? ['id' => $this->receivedBy->id, 'name' => $this->receivedBy->name] : null),
            'items' => GoodsReceivedItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
