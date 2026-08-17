<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attribute
 */
class AttributeResource extends JsonResource
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
            'input_type' => $this->input_type,
            'is_variant_attribute' => $this->is_variant_attribute,
            'status' => $this->status,
            'values' => AttributeValueResource::collection($this->whenLoaded('values')),
        ];
    }
}
