<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttributeValue
 */
class AttributeValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'sort_order' => $this->sort_order,
        ];
    }
}
