<?php

namespace App\Domain\Website\Services;

use App\Domain\Website\Models\Article;
use App\Domain\Website\Models\ArticleCategory;
use App\Domain\Website\Models\ArticleTag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Article
    {
        $businessId = $data['business_id'];

        $article = new Article();
        $article->business_id = $businessId;
        $article->author_id = $data['author_id'] ?? null;
        $article->title = $data['title'];
        $article->slug = $this->uniqueSlug($businessId, $data['title']);
        $article->excerpt = $data['excerpt'] ?? null;
        $article->body = $data['body'];
        $article->seo_title = $data['seo_title'] ?? null;
        $article->seo_description = $data['seo_description'] ?? null;
        $article->category_id = $this->resolveCategory($businessId, $data['category_name'] ?? null)?->id;

        if (! empty($data['featured_image'])) {
            $article->featured_image_path = $this->storeImage($data['featured_image']);
        }

        $status = $data['status'] ?? Article::STATUS_DRAFT;
        $article->status = $status;
        $article->published_at = $status === Article::STATUS_PUBLISHED ? now() : null;

        $article->save();

        $article->tags()->sync($this->resolveTags($businessId, $data['tags'] ?? []));

        return $article->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Article $article, array $data): Article
    {
        if (isset($data['title']) && $data['title'] !== $article->title) {
            $article->title = $data['title'];
            $article->slug = $this->uniqueSlug($article->business_id, $data['title'], $article->id);
        }

        $article->excerpt = $data['excerpt'] ?? $article->excerpt;
        $article->body = $data['body'] ?? $article->body;
        $article->seo_title = $data['seo_title'] ?? $article->seo_title;
        $article->seo_description = $data['seo_description'] ?? $article->seo_description;

        if (array_key_exists('category_name', $data)) {
            $article->category_id = $this->resolveCategory($article->business_id, $data['category_name'])?->id;
        }

        if (! empty($data['featured_image'])) {
            $article->featured_image_path = $this->storeImage($data['featured_image']);
        }

        if (isset($data['status']) && $data['status'] !== $article->status) {
            $article->status = $data['status'];
            if ($data['status'] === Article::STATUS_PUBLISHED && ! $article->published_at) {
                $article->published_at = now();
            } elseif ($data['status'] === Article::STATUS_DRAFT) {
                $article->published_at = null;
            }
        }

        $article->save();

        if (array_key_exists('tags', $data)) {
            $article->tags()->sync($this->resolveTags($article->business_id, $data['tags'] ?? []));
        }

        return $article->refresh();
    }

    public function publish(Article $article): Article
    {
        $article->update(['status' => Article::STATUS_PUBLISHED, 'published_at' => $article->published_at ?? now()]);

        return $article->refresh();
    }

    public function unpublish(Article $article): Article
    {
        $article->update(['status' => Article::STATUS_DRAFT]);

        return $article->refresh();
    }

    public function delete(Article $article): void
    {
        $article->delete();
    }

    private function resolveCategory(string $businessId, ?string $name): ?ArticleCategory
    {
        if (empty($name)) {
            return null;
        }

        $slug = Str::slug($name);

        return ArticleCategory::query()->firstOrCreate(
            ['business_id' => $businessId, 'slug' => $slug],
            ['name' => $name],
        );
    }

    /**
     * @param  array<int, string>  $tagNames
     * @return array<int, string>
     */
    private function resolveTags(string $businessId, array $tagNames): array
    {
        return collect($tagNames)
            ->filter(fn ($name) => ! empty(trim($name)))
            ->map(function (string $name) use ($businessId) {
                $tag = ArticleTag::query()->firstOrCreate(
                    ['business_id' => $businessId, 'slug' => Str::slug($name)],
                    ['name' => trim($name)],
                );

                return $tag->id;
            })
            ->all();
    }

    private function storeImage(UploadedFile $file): string
    {
        $path = $file->store('blog', 'public');

        return Storage::url($path);
    }

    private function uniqueSlug(string $businessId, string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (
            Article::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
