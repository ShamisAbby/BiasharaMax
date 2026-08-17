<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'attributes' => $this->attributes,
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price,
            'wholesale_price' => $this->wholesale_price,
            'status' => $this->status,
        ];
    }
}
