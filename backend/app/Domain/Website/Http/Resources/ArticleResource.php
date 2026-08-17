<?php

namespace App\Domain\Website\Http\Resources;

use App\Domain\Website\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'featured_image_path' => $this->featured_image_path,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])),
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name),
            'created_at' => $this->created_at,
        ];
    }
}
