<?php

namespace App\Domain\Sales\Http\Resources;

use App\Domain\Sales\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalePayment
 */
class SalePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'paid_at' => $this->paid_at,
            'received_by' => $this->receivedBy?->name,
        ];
    }
}
