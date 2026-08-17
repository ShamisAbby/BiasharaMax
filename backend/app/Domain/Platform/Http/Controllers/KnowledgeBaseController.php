<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Support\Models\KnowledgeBaseArticle;
use App\Domain\Support\Models\KnowledgeBaseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = KnowledgeBaseCategory::query()
            ->with(['articles' => fn ($q) => $q->orderBy('title')])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Platform/Operations/Support/KnowledgeBase', [
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'icon' => $c->icon,
                'articles' => $c->articles->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'title' => $a->title,
                    'slug' => $a->slug,
                    'content' => $a->content,
                    'is_published' => $a->is_published,
                    'view_count' => $a->view_count,
                ]),
            ]),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:knowledge_base_categories,slug'],
            'icon' => ['nullable', 'string', 'max:60'],
        ]);

        KnowledgeBaseCategory::query()->create($validated);

        return back()->with('status', 'kb-category-created');
    }

    public function storeArticle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'knowledge_base_category_id' => ['required', 'uuid', 'exists:knowledge_base_categories,id'],
            'type' => ['required', Rule::in([KnowledgeBaseArticle::TYPE_ARTICLE, KnowledgeBaseArticle::TYPE_FAQ, KnowledgeBaseArticle::TYPE_GUIDE])],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:knowledge_base_articles,slug'],
            'content' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);

        KnowledgeBaseArticle::query()->create($validated);

        return back()->with('status', 'kb-article-created');
    }

    public function updateArticle(Request $request, KnowledgeBaseArticle $knowledgeBaseArticle): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);

        $knowledgeBaseArticle->update($validated);

        return back()->with('status', 'kb-article-updated');
    }

    public function destroyArticle(KnowledgeBaseArticle $knowledgeBaseArticle): RedirectResponse
    {
        $knowledgeBaseArticle->delete();

        return back()->with('status', 'kb-article-deleted');
    }
}
