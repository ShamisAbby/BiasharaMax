<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Http\Requests\CategoryStoreRequest;
use App\Domain\Inventory\Http\Requests\CategoryUpdateRequest;
use App\Domain\Inventory\Http\Resources\CategoryResource;
use App\Domain\Inventory\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->where('business_id', $request->user()->business_id)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Categories', [
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function store(CategoryStoreRequest $request): RedirectResponse
    {
        Category::create([
            'business_id' => $request->user()->business_id,
            'slug' => $this->uniqueSlug($request->user()->business_id, $request->validated('name')),
            ...$request->validated(),
        ]);

        return back()->with('status', 'category-created');
    }

    public function update(CategoryUpdateRequest $request, Category $category): RedirectResponse
    {
        $attributes = $request->validated();

        if ($attributes['name'] !== $category->name) {
            $attributes['slug'] = $this->uniqueSlug($category->business_id, $attributes['name'], $category->id);
        }

        $category->update($attributes);

        return back()->with('status', 'category-updated');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->children()->exists() || $category->products()->exists()) {
            return back()->withErrors([
                'category' => 'Move or remove this category\'s subcategories and products before deleting it.',
            ]);
        }

        $category->delete();

        return back()->with('status', 'category-deleted');
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            Category::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->withTrashed()
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
