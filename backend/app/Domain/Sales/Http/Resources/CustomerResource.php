<?php

namespace App\Domain\Sales\Http\Resources;

use App\Domain\Sales\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'customer_type' => $this->customer_type,
            'credit_limit' => $this->credit_limit,
            'current_balance' => $this->current_balance,
            'available_credit' => $this->availableCredit(),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
