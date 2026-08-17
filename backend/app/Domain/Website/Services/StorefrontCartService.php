<?php

namespace App\Domain\Website\Services;

use App\Domain\Inventory\Models\Product;
use Illuminate\Support\Facades\Session;

/**
 * A real, session-scoped shopping cart — no persistent cart table, since
 * this storefront doesn't support cross-device cart recovery (explicitly
 * out of scope). Keyed per business so a visitor browsing two different
 * tenant storefronts in the same browser doesn't mix carts.
 */
class StorefrontCartService
{
    private function key(string $businessId): string
    {
        return "storefront_cart.{$businessId}";
    }

    /**
     * @return array<string, int> product_id => quantity
     */
    public function items(string $businessId): array
    {
        return Session::get($this->key($businessId), []);
    }

    public function add(string $businessId, string $productId, int $quantity): void
    {
        $items = $this->items($businessId);
        $items[$productId] = max(1, ($items[$productId] ?? 0) + $quantity);
        Session::put($this->key($businessId), $items);
    }

    public function update(string $businessId, string $productId, int $quantity): void
    {
        $items = $this->items($businessId);

        if ($quantity <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $quantity;
        }

        Session::put($this->key($businessId), $items);
    }

    public function remove(string $businessId, string $productId): void
    {
        $items = $this->items($businessId);
        unset($items[$productId]);
        Session::put($this->key($businessId), $items);
    }

    public function clear(string $businessId): void
    {
        Session::forget($this->key($businessId));
    }

    /**
     * @return array{lines: array<int, array{product: Product, quantity: int, line_total: string}>, subtotal: string}
     */
    public function summary(string $businessId): array
    {
        $items = $this->items($businessId);

        if (empty($items)) {
            return ['lines' => [], 'subtotal' => '0.00'];
        }

        $products = Product::query()
            ->where('business_id', $businessId)
            ->whereIn('id', array_keys($items))
            ->with(['images' => fn ($q) => $q->orderByDesc('is_primary')->limit(1)])
            ->withSum('inventories as stock_quantity', 'quantity')
            ->withSum('inventories as reserved_quantity', 'reserved_quantity')
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = '0.00';

        foreach ($items as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product) {
                continue;
            }

            $lineTotal = bcmul((string) $product->selling_price, (string) $quantity, 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        return ['lines' => $lines, 'subtotal' => $subtotal];
    }
}
