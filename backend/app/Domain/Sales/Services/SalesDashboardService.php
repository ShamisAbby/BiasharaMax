<?php

namespace App\Domain\Sales\Services;

use App\Domain\Business\Models\Branch;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Shared\Support\DateFormatSql;
use Illuminate\Support\Carbon;

/**
 * Every metric here is computed directly from sales/sale_items/customers
 * rows for the business — nothing estimated or forecasted.
 */
class SalesDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $completedSales = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED);

        $today = (clone $completedSales)->whereDate('created_at', Carbon::today());
        $thisMonth = (clone $completedSales)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            'today_sales_count' => $today->count(),
            'today_revenue' => (float) (clone $today)->sum('total_amount'),
            'today_profit' => $this->profitFor((clone $today)->pluck('id')),
            'month_sales_count' => $thisMonth->count(),
            'month_revenue' => (float) (clone $thisMonth)->sum('total_amount'),
            'month_profit' => $this->profitFor((clone $thisMonth)->pluck('id')),
            'outstanding_credit' => (float) Customer::query()->where('business_id', $businessId)->sum('current_balance'),
            'unpaid_sales_count' => (clone $completedSales)->where('payment_status', '!=', Sale::PAYMENT_STATUS_PAID)->count(),
            'customers_count' => Customer::query()->where('business_id', $businessId)->where('is_active', true)->count(),
        ];
    }

    /**
     * Real gross profit: (unit_price - unit_cost) * quantity, summed across
     * every line item of the given sales. Tax is excluded since it isn't
     * profit. unit_cost is snapshotted on the sale item at sale time, so
     * this stays accurate even if the product's cost later changes.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $saleIds
     */
    private function profitFor($saleIds): float
    {
        if ($saleIds->isEmpty()) {
            return 0.0;
        }

        return (float) SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->whereNotNull('unit_cost')
            ->selectRaw('SUM((unit_price - unit_cost) * quantity) as profit')
            ->value('profit') ?? 0.0;
    }

    /**
     * @return array<int, array{label: string, sales: float, profit: float}>
     */
    public function salesAndProfitTrend(string $businessId): array
    {
        $since = Carbon::now()->subDays(13)->startOfDay();

        $sales = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('created_at', '>=', $since)
            ->get(['id', 'total_amount', 'created_at']);

        $itemsBySale = SaleItem::query()
            ->whereIn('sale_id', $sales->pluck('id'))
            ->whereNotNull('unit_cost')
            ->get(['sale_id', 'unit_price', 'unit_cost', 'quantity'])
            ->groupBy('sale_id');

        $byDay = $sales->groupBy(fn (Sale $sale) => $sale->created_at->format('Y-m-d'));

        $trend = [];

        for ($date = $since->copy(); $date->lte(Carbon::now()); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $daySales = $byDay->get($key, collect());

            $revenue = (float) $daySales->sum('total_amount');
            $profit = (float) $daySales->sum(function (Sale $sale) use ($itemsBySale) {
                return $itemsBySale->get($sale->id, collect())->sum(
                    fn ($item) => ((float) $item->unit_price - (float) $item->unit_cost) * (float) $item->quantity,
                );
            });

            $trend[] = [
                'label' => $date->format('D'),
                'sales' => $revenue,
                'profit' => $profit,
            ];
        }

        return $trend;
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    public function revenueTrend(string $businessId): array
    {
        $since = Carbon::now()->subDays(13)->startOfDay();

        $rows = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('created_at', '>=', $since)
            ->selectRaw(DateFormatSql::daily('created_at')." as day, sum(total_amount) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $trend = [];

        for ($date = $since->copy(); $date->lte(Carbon::now()); $date->addDay()) {
            $key = $date->format('Y-m-d');

            $trend[] = [
                'label' => $date->format('M j'),
                'amount' => (float) ($rows[$key] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * @return array<int, array{product_name: string, quantity_sold: float, revenue: float}>
     */
    public function topSellingProducts(string $businessId, int $limit = 10): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->selectRaw('sale_items.product_name, sum(sale_items.quantity) as quantity_sold, sum(sale_items.line_total) as revenue')
            ->groupBy('sale_items.product_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_name' => $row->product_name,
                'quantity_sold' => (float) $row->quantity_sold,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, sale_number: string, total_amount: float, payment_status: string, status: string, created_at: string}>
     */
    public function recentOrders(string $businessId, int $limit = 5): array
    {
        return Sale::query()
            ->where('business_id', $businessId)
            ->latest()
            ->limit($limit)
            ->get(['id', 'sale_number', 'total_amount', 'payment_status', 'status', 'created_at'])
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total_amount' => (float) $sale->total_amount,
                'payment_status' => $sale->payment_status,
                'status' => $sale->status,
                'created_at' => $sale->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Real revenue per branch this month — only meaningful with more
     * than one branch, so the dashboard simply omits this widget for
     * single-branch businesses rather than showing a list of one.
     *
     * @return array<int, array{branch_id: string, name: string, revenue: float, percent_change: float|null}>
     */
    public function branchPerformance(string $businessId): array
    {
        $branches = Branch::query()->where('business_id', $businessId)->get(['id', 'name']);

        if ($branches->count() < 2) {
            return [];
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $previousMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $completed = Sale::query()->where('business_id', $businessId)->where('status', Sale::STATUS_COMPLETED);

        return $branches->map(function (Branch $branch) use ($completed, $monthStart, $monthEnd, $previousMonthStart, $previousMonthEnd) {
            $thisMonth = (float) (clone $completed)->where('branch_id', $branch->id)->whereBetween('created_at', [$monthStart, $monthEnd])->sum('total_amount');
            $previousMonth = (float) (clone $completed)->where('branch_id', $branch->id)->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->sum('total_amount');

            return [
                'branch_id' => $branch->id,
                'name' => $branch->name,
                'revenue' => $thisMonth,
                'percent_change' => $previousMonth > 0 ? round((($thisMonth - $previousMonth) / $previousMonth) * 100, 1) : null,
            ];
        })
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{payment_method: string, total: float, count: int}>
     */
    public function paymentMethodBreakdown(string $businessId): array
    {
        return Sale::query()
            ->join('sale_payments', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.business_id', $businessId)
            ->selectRaw('sale_payments.payment_method, sum(sale_payments.amount) as total, count(*) as count')
            ->groupBy('sale_payments.payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->all();
    }
}
