<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\CustomerLoyaltyTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerLoyaltyTransaction
 */
class CustomerLoyaltyTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'points' => $this->points,
            'balance_after' => $this->balance_after,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
