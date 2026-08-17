<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\LoyaltyRewardRedemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyRewardRedemption
 */
class LoyaltyRewardRedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'points_spent' => $this->points_spent,
            'redeemed_at' => $this->redeemed_at,
            'fulfilled_at' => $this->fulfilled_at,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            'reward' => $this->whenLoaded('reward', fn () => $this->reward ? ['id' => $this->reward->id, 'name' => $this->reward->name] : null),
        ];
    }
}
