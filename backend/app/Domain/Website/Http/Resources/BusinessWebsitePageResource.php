<?php

namespace App\Domain\Website\Http\Resources;

use App\Domain\Website\Models\BusinessWebsitePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessWebsitePage
 */
class BusinessWebsitePageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'is_enabled' => $this->is_enabled,
            'sort_order' => $this->sort_order,
        ];
    }
}
