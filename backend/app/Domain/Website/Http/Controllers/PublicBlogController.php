<?php

namespace App\Domain\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Business;
use App\Domain\Website\Models\Article;
use Inertia\Inertia;
use Inertia\Response;

class PublicBlogController extends Controller
{
    public function index(Business $business): Response
    {
        $articles = Article::query()
            ->where('business_id', $business->id)
            ->published()
            ->with('category')
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return Inertia::render('Storefront/Blog/Index', [
            'business' => $this->businessInfo($business),
            'articles' => $articles->through(fn (Article $article) => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'featured_image_path' => $article->featured_image_path,
                'published_at' => $article->published_at,
                'category' => $article->category?->only(['name', 'slug']),
            ]),
        ]);
    }

    public function show(Business $business, string $slug): Response
    {
        $article = Article::query()
            ->where('business_id', $business->id)
            ->published()
            ->with(['category', 'tags', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Storefront/Blog/Show', [
            'business' => $this->businessInfo($business),
            'article' => [
                'title' => $article->title,
                'slug' => $article->slug,
                'body' => $article->body,
                'featured_image_path' => $article->featured_image_path,
                'published_at' => $article->published_at,
                'seo_title' => $article->seo_title,
                'seo_description' => $article->seo_description,
                'category' => $article->category?->only(['name', 'slug']),
                'tags' => $article->tags->map->only(['name', 'slug']),
                'author_name' => $article->author?->name,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function businessInfo(Business $business): array
    {
        $business->loadMissing('businessType.websiteTemplate');
        $template = $business->businessType?->websiteTemplate;

        return [
            'name' => $business->name,
            'slug' => $business->slug,
            'logo_path' => $business->logo_path,
            'email' => $business->email,
            'phone' => $business->phone,
            'theme_colors' => $template?->theme_colors,
            'typography' => $template?->typography,
        ];
    }
}
