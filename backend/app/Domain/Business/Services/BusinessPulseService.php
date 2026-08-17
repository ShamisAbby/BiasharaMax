<?php

namespace App\Domain\Business\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use Illuminate\Support\Carbon;

/**
 * A dedicated multi-factor "pulse" breakdown, distinct from the single
 * Business Health score — each factor is its own real, independently
 * computed signal (revenue growth, profit trend, cash flow, inventory
 * health, debt status, customer growth). Factors with no real data
 * source yet (employee activity, website performance, an "AI confidence"
 * score) are deliberately omitted rather than faked.
 */
class BusinessPulseService
{
    /**
     * @param  array<string, mixed>|null  $financials  FinancialReportService::summary() output, or null if Accounting isn't accessible.
     * @param  array<string, mixed>|null  $crm  CrmDashboardService::summary() output, or null if CRM isn't accessible.
     * @param  array<string, mixed>  $inventory  InventoryDashboardService::summary() output.
     * @return array<string, mixed>
     */
    public function compute(Business $business, array $inventory, ?array $financials, ?array $crm): array
    {
        $weekRevenue = $this->revenueSince($business->id, Carbon::now()->subDays(7));
        $previousWeekRevenue = $this->revenueBetween($business->id, Carbon::now()->subDays(14), Carbon::now()->subDays(7));
        $revenueGrowthPercent = $this->percentChange($previousWeekRevenue, $weekRevenue);

        $weekProfit = $this->profitSince($business->id, Carbon::now()->subDays(7));
        $previousWeekProfit = $this->profitBetween($business->id, Carbon::now()->subDays(14), Carbon::now()->subDays(7));
        $profitTrendPercent = $this->percentChange($previousWeekProfit, $weekProfit);

        $cashFlow = null;
        $debtStatus = null;

        if ($financials !== null) {
            $netCash = $financials['cash_balance'] + $financials['bank_balance'];
            $cashFlow = [
                'net_cash' => round($netCash, 2),
                'accounts_payable' => $financials['accounts_payable'],
                'status' => $netCash >= $financials['accounts_payable'] ? 'healthy' : 'tight',
            ];

            $debtStatus = [
                'outstanding_debts' => $financials['outstanding_debts'],
                'accounts_payable' => $financials['accounts_payable'],
                'net_position' => round($financials['outstanding_debts'] - $financials['accounts_payable'], 2),
            ];
        }

        $customerGrowth = $crm !== null ? [
            'new_customers_this_month' => $crm['new_customers_this_month'],
            'total_customers' => $crm['total_customers'],
            'vip_customers' => $crm['vip_customers'],
        ] : null;

        return [
            'revenue_growth' => [
                'percent' => $revenueGrowthPercent,
                'this_week' => round($weekRevenue, 2),
                'previous_week' => round($previousWeekRevenue, 2),
            ],
            'profit_trend' => [
                'percent' => $profitTrendPercent,
                'this_week' => round($weekProfit, 2),
                'previous_week' => round($previousWeekProfit, 2),
            ],
            'inventory_health' => [
                'score' => $inventory['health_score'],
                'low_stock_count' => $inventory['low_stock_count'],
                'expiring_soon_count' => $inventory['expiring_soon_count'],
            ],
            'cash_flow' => $cashFlow,
            'debt_status' => $debtStatus,
            'customer_growth' => $customerGrowth,
        ];
    }

    private function revenueSince(string $businessId, Carbon $since): float
    {
        return (float) Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('created_at', '>=', $since)
            ->sum('total_amount');
    }

    private function revenueBetween(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');
    }

    private function profitSince(string $businessId, Carbon $since): float
    {
        return $this->profitForSaleIds(
            Sale::query()
                ->where('business_id', $businessId)
                ->where('status', Sale::STATUS_COMPLETED)
                ->where('created_at', '>=', $since)
                ->pluck('id'),
        );
    }

    private function profitBetween(string $businessId, Carbon $from, Carbon $to): float
    {
        return $this->profitForSaleIds(
            Sale::query()
                ->where('business_id', $businessId)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereBetween('created_at', [$from, $to])
                ->pluck('id'),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $saleIds
     */
    private function profitForSaleIds($saleIds): float
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

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
