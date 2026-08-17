<?php

namespace App\Domain\Sales\Http\Resources;

use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleReturn
 */
class SaleReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'status' => $this->status,
            'reason' => $this->reason,
            'refund_method' => $this->refund_method,
            'refund_amount' => $this->refund_amount,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at,
            'sale' => $this->whenLoaded('sale', fn () => $this->sale ? [
                'id' => $this->sale->id,
                'sale_number' => $this->sale->sale_number,
                'total_amount' => $this->sale->total_amount,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? ['id' => $this->approvedBy->id, 'name' => $this->approvedBy->name] : null),
            'items' => SaleReturnItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
