<?php

namespace App\Domain\Website\Http\Resources;

use App\Domain\Inventory\Models\Product;
use App\Domain\Website\Services\StorefrontCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately exposes only customer-safe fields — never cost price,
 * supplier, reorder thresholds, or other internal Inventory data that
 * the admin-facing ProductResource includes.
 *
 * @mixin Product
 */
class StorefrontProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availableStock = app(StorefrontCatalogService::class)->availableStock($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'selling_price' => $this->selling_price,
            'track_stock' => $this->track_stock,
            'in_stock' => ! $this->track_stock || $availableStock > 0,
            'available_stock' => $this->track_stock ? $availableStock : null,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ] : null),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'path' => $image->path,
                'alt_text' => $image->alt_text,
            ])),
        ];
    }
}
