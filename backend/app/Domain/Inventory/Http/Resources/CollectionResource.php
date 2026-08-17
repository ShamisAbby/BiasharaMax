<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Collection
 */
class CollectionResource extends JsonResource
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
            'image_path' => $this->image_path,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
