<?php

namespace App\Domain\Purchasing\Http\Resources;

use App\Domain\Purchasing\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierPayment
 */
class SupplierPaymentResource extends JsonResource
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
            'paid_by' => $this->paidBy?->name,
        ];
    }
}
