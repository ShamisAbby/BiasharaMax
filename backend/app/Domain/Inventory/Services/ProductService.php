<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $businessId, array $data): Product
    {
        return DB::transaction(function () use ($businessId, $data) {
            $product = Product::create([
                'business_id' => $businessId,
                ...$this->productAttributes($data),
                'slug' => $this->uniqueSlug($businessId, $data['name']),
                'sku' => $data['sku'] ?: $this->generateSku($businessId),
            ]);

            $this->syncTaxonomy($product, $data);
            $this->syncVariants($product, $data['variants'] ?? []);

            return $product->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $attributes = $this->productAttributes($data);

            if ($data['name'] !== $product->name) {
                $attributes['slug'] = $this->uniqueSlug($product->business_id, $data['name'], $product->id);
            }

            $product->update($attributes);

            $this->syncTaxonomy($product, $data);

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants']);
            }

            return $product->refresh();
        });
    }

    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $copy = $product->replicate(['sku', 'barcode', 'slug']);
            $copy->name = "{$product->name} (Copy)";
            $copy->slug = $this->uniqueSlug($product->business_id, $copy->name);
            $copy->sku = $this->generateSku($product->business_id);
            $copy->barcode = null;
            $copy->save();

            $copy->tags()->sync($product->tags()->pluck('tags.id'));
            $copy->collections()->sync($product->collections()->pluck('collections.id'));

            foreach ($product->variants as $variant) {
                $variantCopy = $variant->replicate(['sku', 'barcode']);
                $variantCopy->product_id = $copy->id;
                $variantCopy->sku = $this->generateVariantSku($copy);
                $variantCopy->barcode = null;
                $variantCopy->save();
            }

            return $copy;
        });
    }

    public function archive(Product $product): void
    {
        $product->update(['status' => Product::STATUS_ARCHIVED]);
    }

    public function generateSku(string $businessId): string
    {
        $count = Product::query()->where('business_id', $businessId)->withTrashed()->count();

        do {
            $count++;
            $candidate = 'PRD-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
        } while (Product::query()->where('business_id', $businessId)->where('sku', $candidate)->withTrashed()->exists());

        return $candidate;
    }

    private function generateVariantSku(Product $product): string
    {
        $count = ProductVariant::query()->where('product_id', $product->id)->withTrashed()->count() + 1;

        return "{$product->sku}-V{$count}";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function productAttributes(array $data): array
    {
        return [
            'category_id' => $data['category_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'default_supplier_id' => $data['default_supplier_id'] ?? null,
            'name' => $data['name'],
            'barcode' => $data['barcode'] ?? null,
            'custom_code' => $data['custom_code'] ?? null,
            'description' => $data['description'] ?? null,
            'product_type' => $data['product_type'] ?? Product::TYPE_SIMPLE,
            'track_stock' => $data['track_stock'] ?? true,
            'has_expiry' => $data['has_expiry'] ?? false,
            'has_batch' => $data['has_batch'] ?? false,
            'has_serial' => $data['has_serial'] ?? false,
            'cost_price' => $data['cost_price'] ?? 0,
            'purchase_price' => $data['purchase_price'] ?? 0,
            'selling_price' => $data['selling_price'] ?? 0,
            'wholesale_price' => $data['wholesale_price'] ?? null,
            'minimum_price' => $data['minimum_price'] ?? null,
            'tax_rate' => $data['tax_rate'] ?? 0,
            'minimum_stock' => $data['minimum_stock'] ?? 0,
            'maximum_stock' => $data['maximum_stock'] ?? null,
            'reorder_level' => $data['reorder_level'] ?? 0,
            'weight' => $data['weight'] ?? null,
            'weight_unit' => $data['weight_unit'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'status' => $data['status'] ?? Product::STATUS_ACTIVE,
            'visibility' => $data['visibility'] ?? Product::VISIBILITY_VISIBLE,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncTaxonomy(Product $product, array $data): void
    {
        if (array_key_exists('tag_ids', $data)) {
            $product->tags()->sync($data['tag_ids'] ?? []);
        }

        if (array_key_exists('collection_ids', $data)) {
            $product->collections()->sync($data['collection_ids'] ?? []);
        }

        if (array_key_exists('supplier_ids', $data)) {
            $product->suppliers()->sync($data['supplier_ids'] ?? []);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $keepIds = [];

        foreach ($variants as $variantData) {
            $variant = isset($variantData['id'])
                ? $product->variants()->find($variantData['id'])
                : null;

            $attributes = [
                'sku' => $variantData['sku'] ?: ($variant?->sku ?? "{$product->sku}-V".($product->variants()->count() + 1)),
                'barcode' => $variantData['barcode'] ?? null,
                'attributes' => $variantData['attributes'] ?? [],
                'cost_price' => $variantData['cost_price'] ?? null,
                'selling_price' => $variantData['selling_price'] ?? null,
                'wholesale_price' => $variantData['wholesale_price'] ?? null,
                'status' => $variantData['status'] ?? ProductVariant::STATUS_ACTIVE,
            ];

            if ($variant !== null) {
                $variant->update($attributes);
            } else {
                $variant = $product->variants()->create([
                    'business_id' => $product->business_id,
                    ...$attributes,
                ]);
            }

            $keepIds[] = $variant->id;
        }

        if ($keepIds !== []) {
            $product->variants()->whereNotIn('id', $keepIds)->delete();
        }
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreProductId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            Product::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->when($ignoreProductId, fn ($query) => $query->whereKeyNot($ignoreProductId))
                ->withTrashed()
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
