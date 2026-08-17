<?php

namespace App\Domain\Website\Http\Resources;

use App\Domain\Website\Models\BusinessWebsite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessWebsite
 */
class BusinessWebsiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'published_at' => $this->published_at,
            'template_name' => $this->whenLoaded('template', fn () => $this->template?->name),
            'pages' => BusinessWebsitePageResource::collection($this->whenLoaded('pages')),
        ];
    }
}
