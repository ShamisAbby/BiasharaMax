<?php

namespace App\Domain\Website\Services;

use App\Domain\Sales\Models\Sale;
use App\Domain\Website\Models\ProductEnquiry;
use Illuminate\Support\Carbon;

/**
 * Every figure here is computed live from real Sale (source=online) and
 * ProductEnquiry rows — no separate "website analytics" tracking table.
 */
class WebsiteDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        $onlineSales = Sale::query()
            ->where('business_id', $businessId)
            ->where('source', Sale::SOURCE_ONLINE)
            ->where('status', Sale::STATUS_COMPLETED);

        return [
            'online_orders_this_month' => (clone $onlineSales)->where('created_at', '>=', $monthStart)->count(),
            'online_revenue_this_month' => (float) (clone $onlineSales)->where('created_at', '>=', $monthStart)->sum('total_amount'),
            'online_orders_total' => (clone $onlineSales)->count(),
            'open_enquiries_count' => ProductEnquiry::query()
                ->where('business_id', $businessId)
                ->where('status', ProductEnquiry::STATUS_NEW)
                ->count(),
        ];
    }

    /**
     * @return array<int, array{sale_number: string, customer_name: string, total_amount: string, created_at: string}>
     */
    public function recentOrders(string $businessId, int $limit = 5): array
    {
        return Sale::query()
            ->where('business_id', $businessId)
            ->where('source', Sale::SOURCE_ONLINE)
            ->with('customer:id,name')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Sale $sale) => [
                'sale_number' => $sale->sale_number,
                'customer_name' => $sale->customer?->name ?? 'Guest',
                'total_amount' => (string) $sale->total_amount,
                'created_at' => $sale->created_at->toIso8601String(),
            ])
            ->all();
    }
}
