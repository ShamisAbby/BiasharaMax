<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every metric here is derived strictly from data the Inventory module
 * owns. "Top Selling Products" and "Most Viewed Products" are
 * deliberately not included — they depend on Sales (not built yet) and
 * page-view analytics (doesn't exist anywhere in the platform). Faking
 * them would violate the "no placeholders" rule.
 */
class InventoryDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $inventoryQuery = Inventory::query()->where('business_id', $businessId);

        $totalProducts = Product::query()->where('business_id', $businessId)->where('status', '!=', Product::STATUS_ARCHIVED)->count();
        $lowStockCount = (clone $inventoryQuery)->where('quantity', '>', 0)->where('quantity', '<', Inventory::LOW_STOCK_THRESHOLD)->count();
        $outOfStockCount = (clone $inventoryQuery)->where('quantity', '<=', 0)->count();

        $expiredCount = ProductBatch::query()
            ->where('business_id', $businessId)
            ->where('expiry_date', '<', now())
            ->where('quantity', '>', 0)
            ->count();

        $expiringSoonCount = ProductBatch::query()
            ->where('business_id', $businessId)
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->where('quantity', '>', 0)
            ->count();

        $inventoryValue = (clone $inventoryQuery)->sum(
            DB::raw('quantity * average_cost'),
        );

        $todayMovements = StockMovement::query()
            ->where('business_id', $businessId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $recentProducts = Product::query()
            ->where('business_id', $businessId)
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'sku', 'selling_price', 'created_at']);

        return [
            'total_products' => $totalProducts,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'expired_count' => $expiredCount,
            'expiring_soon_count' => $expiringSoonCount,
            'inventory_value' => round((float) $inventoryValue, 2),
            'today_stock_movements' => $todayMovements,
            'recent_products' => $recentProducts,
            'health_score' => $this->healthScore($totalProducts, $lowStockCount, $outOfStockCount, $expiredCount),
            'stock_movement_trend' => $this->stockMovementTrend($businessId),
            'stock_health_breakdown' => $this->stockHealthBreakdown($totalProducts, $lowStockCount, $outOfStockCount, $expiredCount, $expiringSoonCount),
        ];
    }

    /**
     * Products with quantity > 0 and quantity < LOW_STOCK_THRESHOLD —
     * same definition as `low_stock_count` in summary() — for widgets
     * that need to list them, not just count them.
     *
     * @return array<int, array{product_id: string, name: string, quantity: float, threshold: int}>
     */
    public function lowStockProducts(string $businessId, int $limit = 5): array
    {
        return Inventory::query()
            ->where('business_id', $businessId)
            ->where('quantity', '>', 0)
            ->where('quantity', '<', Inventory::LOW_STOCK_THRESHOLD)
            ->with('product:id,name')
            ->orderBy('quantity')
            ->limit($limit)
            ->get()
            ->filter(fn (Inventory $row) => $row->product !== null)
            ->map(fn (Inventory $row) => [
                'product_id' => $row->product->id,
                'name'       => $row->product->name,
                'quantity'   => (float) $row->quantity,
                'threshold'  => Inventory::LOW_STOCK_THRESHOLD,
            ])
            ->values()
            ->all();
    }

    /**
     * Daily inbound vs outbound movement totals for the last 14 days,
     * read straight from the immutable stock_movements ledger.
     *
     * @return array<int, array{date: string, inbound: float, outbound: float}>
     */
    private function stockMovementTrend(string $businessId): array
    {
        $since = Carbon::today()->subDays(13);

        $rows = StockMovement::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, direction, SUM(quantity) as total')
            ->groupBy('day', 'direction')
            ->get()
            ->groupBy('day');

        $trend = [];

        for ($date = $since->copy(); $date->lte(Carbon::today()); $date->addDay()) {
            $day = $date->toDateString();
            $dayRows = $rows->get($day, collect());

            $trend[] = [
                'date' => $day,
                'inbound' => (float) ($dayRows->firstWhere('direction', StockMovement::DIRECTION_IN)->total ?? 0),
                'outbound' => (float) ($dayRows->firstWhere('direction', StockMovement::DIRECTION_OUT)->total ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * @return array<string, int>
     */
    private function stockHealthBreakdown(int $total, int $lowStock, int $outOfStock, int $expired, int $expiringSoon): array
    {
        $healthy = max(0, $total - $lowStock - $outOfStock);

        return [
            'healthy' => $healthy,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
        ];
    }

    /**
     * A simple, transparent score (0-100): start at 100, subtract a
     * proportional penalty for each unhealthy product. Not a black box —
     * every point lost is directly explainable to the business owner.
     */
    private function healthScore(int $total, int $lowStock, int $outOfStock, int $expired): int
    {
        if ($total === 0) {
            return 100;
        }

        $penalty = (($lowStock * 1) + ($outOfStock * 2) + ($expired * 2)) / $total * 100;

        return (int) max(0, 100 - round($penalty));
    }
}
