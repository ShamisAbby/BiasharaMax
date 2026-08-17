<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\CustomerTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerTag
 */
class CustomerTagResource extends JsonResource
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
            'color' => $this->color,
            'customers_count' => $this->whenCounted('customers'),
            'created_at' => $this->created_at,
        ];
    }
}
