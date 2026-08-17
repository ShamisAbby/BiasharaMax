<?php

namespace App\Domain\Website\Services;

use App\Domain\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads real Inventory/Product data for the public storefront — never a
 * separate "storefront product" record. Visibility is governed by the
 * Product module's own `status`/`visibility` fields so a product hidden
 * or archived in Inventory disappears from the website immediately.
 */
class StorefrontCatalogService
{
    /**
     * @return Builder<Product>
     */
    private function visibleProducts(string $businessId): Builder
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('visibility', Product::VISIBILITY_VISIBLE)
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')])
            ->withSum('inventories as stock_quantity', 'quantity')
            ->withSum('inventories as reserved_quantity', 'reserved_quantity');
    }

    public function paginate(string $businessId, ?string $search = null, ?string $categoryId = null, int $perPage = 12): LengthAwarePaginator
    {
        return $this->visibleProducts($businessId)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(string $businessId, string $slug): ?Product
    {
        return $this->visibleProducts($businessId)
            ->where('slug', $slug)
            ->first();
    }

    public function availableStock(Product $product): float
    {
        if (! $product->track_stock) {
            return INF;
        }

        return max(0, (float) ($product->stock_quantity ?? 0) - (float) ($product->reserved_quantity ?? 0));
    }
}
