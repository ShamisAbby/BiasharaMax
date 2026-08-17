<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'products_count' => $this->whenCounted('products'),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
