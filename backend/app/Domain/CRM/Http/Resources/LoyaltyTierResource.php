<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\LoyaltyTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyTier
 */
class LoyaltyTierResource extends JsonResource
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
            'minimum_spend' => $this->minimum_spend,
            'sort_order' => $this->sort_order,
            'benefits_description' => $this->benefits_description,
            'customers_count' => $this->whenCounted('customers'),
        ];
    }
}
