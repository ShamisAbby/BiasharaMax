<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\LoyaltyReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyReward
 */
class LoyaltyRewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'points_cost' => $this->points_cost,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
            'in_stock' => $this->isInStock(),
            'redemptions_count' => $this->whenCounted('redemptions'),
        ];
    }
}
