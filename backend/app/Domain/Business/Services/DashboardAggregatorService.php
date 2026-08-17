<?php

namespace App\Domain\Business\Services;

use App\Domain\Accounting\Services\FinancialReportService;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\CRM\Services\CrmDashboardService;
use App\Domain\Finance\Services\FinanceDashboardService;
use App\Domain\Inventory\Services\InventoryDashboardService;
use App\Domain\Purchasing\Services\PurchasingDashboardService;
use App\Domain\Sales\Services\SalesDashboardService;
use Illuminate\Support\Facades\Cache;

/**
 * Composes every dashboard widget's data behind a single call so the
 * Business Owner Dashboard route stays one request — each section is
 * still backed by its own module's real service (Inventory, Sales,
 * Accounting, CRM); this just assembles them and applies the same
 * permission gating the dashboard already relied on.
 */
class DashboardAggregatorService
{
    public function __construct(
        private readonly InventoryDashboardService $inventoryDashboard,
        private readonly SalesDashboardService $salesDashboard,
        private readonly FinancialReportService $financialReport,
        private readonly CrmDashboardService $crmDashboard,
        private readonly PurchasingDashboardService $purchasingDashboard,
        private readonly BusinessHealthService $businessHealth,
        private readonly BusinessPulseService $businessPulse,
        private readonly RecentActivityService $recentActivity,
        private readonly FinanceDashboardService $financeDashboard,
    ) {}

    /**
     * How long a dashboard snapshot stays warm.
     *
     * The widgets are ~100 aggregate queries across nine services, and they
     * were re-run in full on every visit — including every browser Back
     * into the dashboard. Sixty seconds is short enough that a shop owner
     * watching sales come in still sees them appear, and long enough to
     * absorb the navigation churn that was doing the real damage.
     */
    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?Business $business): array
    {
        if (! $business) {
            return [
                'inventory' => null,
                'sales' => null,
                'financials' => null,
                'crm' => null,
                'businessHealth' => null,
                'businessPulse' => null,
                'recentActivity' => [],
                'lowStockProducts' => [],
                'branchPerformance' => [],
                'purchasing' => null,
                'finance' => null,
            ];
        }

        // Keyed by permission set, not just by business. Two employees of
        // the same business can be entitled to different widgets, so a
        // business-only key would serve one of them the other's data —
        // the cache must never be able to widen what someone can see.
        $cacheKey = sprintf(
            'dashboard:%s:%s',
            $business->getKey(),
            substr(hash('xxh128', implode('|', $this->relevantPermissions($user))), 0, 16),
        );

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->compute($user, $business),
        );
    }

    /**
     * The permissions that actually change the shape of the response.
     *
     * Hashing the user's whole permission set would work but would split
     * the cache far more finely than necessary — an owner and a manager
     * with identical dashboard entitlements should share an entry.
     *
     * @return list<string>
     */
    private function relevantPermissions(User $user): array
    {
        $gates = [
            'inventory.view',
            'sales.view',
            'accounting.view',
            'crm.view',
            'purchase_orders.view',
            'finance.view',
        ];

        return array_values(array_filter(
            $gates,
            fn (string $permission): bool => $user->hasPermission($permission),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(User $user, Business $business): array
    {
        $inventory = $user->hasPermission('inventory.view')
            ? $this->inventoryDashboard->summary($business->id)
            : null;

        $sales = $user->hasPermission('sales.view')
            ? [
                'summary' => $this->salesDashboard->summary($business->id),
                'trend' => $this->salesDashboard->salesAndProfitTrend($business->id),
                'topProducts' => $this->salesDashboard->topSellingProducts($business->id, 5),
                'paymentMethods' => $this->salesDashboard->paymentMethodBreakdown($business->id),
                'recentOrders' => $this->salesDashboard->recentOrders($business->id, 5),
            ]
            : null;

        $financials = $user->hasPermission('accounting.view')
            ? $this->financialReport->summary($business->id)
            : null;

        $crm = $user->hasPermission('crm.view')
            ? $this->crmDashboard->summary($business->id)
            : null;

        $purchasing = $user->hasPermission('purchase_orders.view')
            ? $this->purchasingDashboard->summary($business->id)
            : null;

        $finance = $user->hasPermission('finance.view')
            ? $this->financeDashboard->summary($business->id)
            : null;

        return [
            'inventory' => $inventory,
            'sales' => $sales,
            'financials' => $financials,
            'crm' => $crm,
            'purchasing' => $purchasing,
            'finance' => $finance,
            // Gated like everything around it. The health score is derived
            // from revenue and margin, and recent activity lists sales,
            // purchases and stock movements by name — both were being sent
            // to every employee regardless of what they were allowed to
            // see, which made them a way around the six checks above.
            'businessHealth' => $user->hasPermission('sales.view') || $user->hasPermission('accounting.view')
                ? $this->businessHealth->compute($business)
                : null,
            'businessPulse' => $inventory ? $this->businessPulse->compute($business, $inventory, $financials, $crm) : null,
            'recentActivity' => $user->hasPermission('sales.view') || $user->hasPermission('inventory.view')
                ? $this->recentActivity->recent($business->id)
                : [],
            'lowStockProducts' => $user->hasPermission('inventory.view') ? $this->inventoryDashboard->lowStockProducts($business->id) : [],
            'branchPerformance' => $user->hasPermission('sales.view') ? $this->salesDashboard->branchPerformance($business->id) : [],
        ];
    }
}
