<?php

namespace App\Domain\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Website\Http\Requests\ArticleStoreRequest;
use App\Domain\Website\Http\Requests\ArticleUpdateRequest;
use App\Domain\Website\Http\Resources\ArticleResource;
use App\Domain\Website\Models\Article;
use App\Domain\Website\Models\ArticleCategory;
use App\Domain\Website\Models\ArticleTag;
use App\Domain\Website\Services\ArticleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleService $articleService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Article::class);

        $businessId = $request->user()->business_id;

        $articles = Article::query()
            ->where('business_id', $businessId)
            ->with(['category', 'tags'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Website/Blog/Index', [
            'articles' => ArticleResource::collection($articles),
            'categories' => ArticleCategory::query()->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
            'tags' => ArticleTag::query()->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(Article $article): Response
    {
        $this->authorize('view', $article);

        $article->load(['category', 'tags', 'author']);

        return Inertia::render('Website/Blog/Edit', [
            'article' => new ArticleResource($article),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Article::class);

        return Inertia::render('Website/Blog/Edit', [
            'article' => null,
        ]);
    }

    public function store(ArticleStoreRequest $request): RedirectResponse
    {
        $article = $this->articleService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
            'author_id' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('website.blog.show', $article)->with('status', 'article-created');
    }

    public function update(ArticleUpdateRequest $request, Article $article): RedirectResponse
    {
        $this->articleService->update($article, [
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'article-updated');
    }

    public function publish(Article $article): RedirectResponse
    {
        $this->authorize('manage', $article);
        $this->articleService->publish($article);

        return back()->with('status', 'article-published');
    }

    public function unpublish(Article $article): RedirectResponse
    {
        $this->authorize('manage', $article);
        $this->articleService->unpublish($article);

        return back()->with('status', 'article-unpublished');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('manage', $article);
        $this->articleService->delete($article);

        return redirect()->route('website.blog.index')->with('status', 'article-deleted');
    }
}
