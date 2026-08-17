<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Http\Requests\BrandStoreRequest;
use App\Domain\Inventory\Http\Requests\BrandUpdateRequest;
use App\Domain\Inventory\Http\Resources\BrandResource;
use App\Domain\Inventory\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Brand::class);

        $brands = Brand::query()
            ->where('business_id', $request->user()->business_id)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Brands', [
            'brands' => BrandResource::collection($brands),
        ]);
    }

    public function store(BrandStoreRequest $request): RedirectResponse
    {
        Brand::create([
            'business_id' => $request->user()->business_id,
            'slug' => $this->uniqueSlug($request->user()->business_id, $request->validated('name')),
            ...$request->validated(),
        ]);

        return back()->with('status', 'brand-created');
    }

    public function update(BrandUpdateRequest $request, Brand $brand): RedirectResponse
    {
        $attributes = $request->validated();

        if ($attributes['name'] !== $brand->name) {
            $attributes['slug'] = $this->uniqueSlug($brand->business_id, $attributes['name'], $brand->id);
        }

        $brand->update($attributes);

        return back()->with('status', 'brand-updated');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        if ($brand->products()->exists()) {
            return back()->withErrors(['brand' => 'Reassign this brand\'s products before deleting it.']);
        }

        $brand->delete();

        return back()->with('status', 'brand-deleted');
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            Brand::query()
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
