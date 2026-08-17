<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Http\Requests\CollectionStoreRequest;
use App\Domain\Inventory\Http\Requests\CollectionUpdateRequest;
use App\Domain\Inventory\Http\Resources\CollectionResource;
use App\Domain\Inventory\Models\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Collection::class);

        $collections = Collection::query()
            ->where('business_id', $request->user()->business_id)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Collections', [
            'collections' => CollectionResource::collection($collections),
        ]);
    }

    public function store(CollectionStoreRequest $request): RedirectResponse
    {
        Collection::create([
            'business_id' => $request->user()->business_id,
            'slug' => $this->uniqueSlug($request->user()->business_id, $request->validated('name')),
            ...$request->validated(),
        ]);

        return back()->with('status', 'collection-created');
    }

    public function update(CollectionUpdateRequest $request, Collection $collection): RedirectResponse
    {
        $attributes = $request->validated();

        if ($attributes['name'] !== $collection->name) {
            $attributes['slug'] = $this->uniqueSlug($collection->business_id, $attributes['name'], $collection->id);
        }

        $collection->update($attributes);

        return back()->with('status', 'collection-updated');
    }

    public function destroy(Collection $collection): RedirectResponse
    {
        $this->authorize('delete', $collection);

        $collection->delete();

        return back()->with('status', 'collection-deleted');
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            Collection::query()
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
