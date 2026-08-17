<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Http\Resources\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'custom_code' => $this->custom_code,
            'description' => $this->description,
            'product_type' => $this->product_type,
            'track_stock' => $this->track_stock,
            'has_expiry' => $this->has_expiry,
            'has_batch' => $this->has_batch,
            'has_serial' => $this->has_serial,
            'cost_price' => $this->cost_price,
            'purchase_price' => $this->purchase_price,
            'selling_price' => $this->selling_price,
            'wholesale_price' => $this->wholesale_price,
            'minimum_price' => $this->minimum_price,
            'tax_rate' => $this->tax_rate,
            'last_purchase_price' => $this->last_purchase_price,
            'last_purchase_at' => $this->last_purchase_at,
            'minimum_stock' => $this->minimum_stock,
            'maximum_stock' => $this->maximum_stock,
            'reorder_level' => $this->reorder_level,
            'weight' => $this->weight,
            'weight_unit' => $this->weight_unit,
            'dimensions' => $this->dimensions,
            'status' => $this->status,
            'visibility' => $this->visibility,

            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'default_supplier' => new SupplierResource($this->whenLoaded('defaultSupplier')),

            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'collections' => CollectionResource::collection($this->whenLoaded('collections')),
            'suppliers' => SupplierResource::collection($this->whenLoaded('suppliers')),
            'inventories' => InventoryResource::collection($this->whenLoaded('inventories')),

            'total_quantity' => $this->when(
                $this->relationLoaded('inventories'),
                fn () => $this->inventories->sum('quantity'),
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
