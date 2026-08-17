<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerGroup
 */
class CustomerGroupResource extends JsonResource
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
            'is_vip' => $this->is_vip,
            'discount_percentage' => $this->discount_percentage,
            'customers_count' => $this->whenCounted('customers'),
            'created_at' => $this->created_at,
        ];
    }
}
