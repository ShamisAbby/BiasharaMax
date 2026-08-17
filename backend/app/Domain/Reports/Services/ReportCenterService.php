<?php

namespace App\Domain\Reports\Services;

use App\Domain\Accounting\Services\FinancialReportService as AccountingReportService;
use App\Domain\AiInsights\Services\BusinessAssistantService;
use App\Domain\Business\Services\BusinessHealthService;
use App\Domain\CRM\Services\CrmDashboardService;
use App\Domain\Finance\Models\FixedAsset;
use App\Domain\Finance\Services\BudgetService;
use App\Domain\Finance\Services\FinancialStatementService;
use App\Domain\Finance\Services\GeneralLedgerService;
use App\Domain\Finance\Services\TaxService;
use App\Domain\Inventory\Services\InventoryDashboardService;
use App\Domain\Payroll\Models\PayrollPeriod;
use App\Domain\Payroll\Models\Payslip;
use App\Domain\Purchasing\Services\PurchasingDashboardService;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Shared\Support\DateFormatSql;
use App\Domain\Sales\Services\SalesDashboardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportCenterService
{
    public function __construct(
        private readonly SalesDashboardService $salesDashboard,
        private readonly InventoryDashboardService $inventoryDashboard,
        private readonly PurchasingDashboardService $purchasingDashboard,
        private readonly CrmDashboardService $crmDashboard,
        private readonly FinancialStatementService $financialStatements,
        private readonly GeneralLedgerService $generalLedger,
        private readonly BudgetService $budgetService,
        private readonly AccountingReportService $accountingReports,
        private readonly BusinessHealthService $businessHealth,
    ) {}

    /**
     * Full report catalog — every entry the hub renders.
     * `route` = named Ziggy route (links to dedicated page).
     * `action` = inline key handled by generate() (no dedicated page yet).
     * Only one of route/action is set per entry.
     *
     * @return array<string, array<int, array{key:string,label:string,description:string,route?:string,action?:string,available:bool}>>
     */
    public function catalog(): array
    {
        return [
            'Sales' => [
                ['key' => 'sales.daily',          'label' => 'Daily Sales',           'description' => 'Sales transactions for today',                 'action' => 'sales.daily',          'available' => true],
                ['key' => 'sales.monthly',         'label' => 'Monthly Sales',         'description' => 'Sales totals grouped by month',               'action' => 'sales.monthly',         'available' => true],
                ['key' => 'sales.by_product',      'label' => 'Sales by Product',      'description' => 'Top selling products by revenue',             'action' => 'sales.by_product',      'available' => true],
                ['key' => 'sales.by_branch',       'label' => 'Sales by Branch',       'description' => 'Revenue breakdown across branches',           'action' => 'sales.by_branch',       'available' => true],
                ['key' => 'sales.by_payment',      'label' => 'Sales by Payment',      'description' => 'Revenue split by payment method',             'action' => 'sales.by_payment',      'available' => true],
                ['key' => 'sales.top_customers',   'label' => 'Top Customers',         'description' => 'Customers ranked by purchase value',          'action' => 'sales.top_customers',   'available' => true],
                ['key' => 'sales.returns',         'label' => 'Sales Returns',         'description' => 'Returned items and refund totals',            'action' => 'sales.returns',         'available' => true],
                ['key' => 'sales.slow_moving',     'label' => 'Slow Moving Products',  'description' => 'Products with no sales in last 30 days',     'action' => 'sales.slow_moving',     'available' => true],
                ['key' => 'sales.customer_debts',  'label' => 'Customer Debts',        'description' => 'Customers with outstanding credit balances',  'action' => 'sales.customer_debts',  'available' => true],
            ],
            'Inventory' => [
                ['key' => 'inventory.stock',       'label' => 'Current Stock',         'description' => 'Live inventory quantities across warehouses', 'action' => 'inventory.stock',       'available' => true],
                ['key' => 'inventory.low_stock',   'label' => 'Low Stock',             'description' => 'Items at or below reorder level',            'action' => 'inventory.low_stock',   'available' => true],
                ['key' => 'inventory.out_of_stock','label' => 'Out of Stock',          'description' => 'Items with zero quantity',                   'action' => 'inventory.out_of_stock','available' => true],
                ['key' => 'inventory.dead_stock',  'label' => 'Dead Stock',            'description' => 'Stock with no movement in 90+ days',         'action' => 'inventory.dead_stock',  'available' => true],
                ['key' => 'inventory.expired',     'label' => 'Expired Products',      'description' => 'Batches past their expiry date',             'action' => 'inventory.expired',     'available' => true],
                ['key' => 'inventory.movements',   'label' => 'Stock Movements',       'description' => 'All stock movement activity',                'action' => 'inventory.movements',   'available' => true],
                ['key' => 'inventory.valuation',   'label' => 'Inventory Valuation',   'description' => 'Total stock value at average cost',          'action' => 'inventory.valuation',   'available' => true],
            ],
            'Purchasing' => [
                ['key' => 'purchasing.orders',     'label' => 'Purchase Orders',       'description' => 'All purchase orders with status',            'action' => 'purchasing.orders',     'available' => true],
                ['key' => 'purchasing.suppliers',  'label' => 'Supplier Performance',  'description' => 'Top suppliers by purchase value',            'action' => 'purchasing.suppliers',  'available' => true],
                ['key' => 'purchasing.lead_times', 'label' => 'Supplier Lead Times',   'description' => 'Average delivery time per supplier',         'action' => 'purchasing.lead_times', 'available' => true],
                ['key' => 'purchasing.trend',      'label' => 'Purchase Trends',       'description' => 'Weekly purchasing volume trends',            'action' => 'purchasing.trend',      'available' => true],
            ],
            'Finance' => [
                ['key' => 'finance.trial_balance', 'label' => 'Trial Balance',         'description' => 'Debit/credit totals for all accounts',       'route'  => 'finance.reports.trial-balance',    'available' => true],
                ['key' => 'finance.profit_loss',   'label' => 'Profit & Loss',         'description' => 'Income statement for a chosen period',       'route'  => 'finance.reports.profit-and-loss',  'available' => true],
                ['key' => 'finance.balance_sheet', 'label' => 'Balance Sheet',         'description' => 'Assets, liabilities and equity snapshot',    'route'  => 'finance.reports.balance-sheet',    'available' => true],
                ['key' => 'finance.cash_flow',     'label' => 'Cash Flow Statement',   'description' => 'Operating cash movements (indirect method)', 'route'  => 'finance.reports.cash-flow',         'available' => true],
                ['key' => 'finance.budget_actual', 'label' => 'Budget vs Actual',      'description' => 'Variance between planned and actual spend',  'route'  => 'finance.budgets.index',            'available' => true],
                ['key' => 'finance.vat',           'label' => 'VAT Report',            'description' => 'Output vs input VAT for a period',          'route'  => 'finance.tax.vat-return',           'available' => true],
                ['key' => 'finance.income_tax',    'label' => 'Income Tax',            'description' => 'Estimated income tax liability',             'route'  => 'finance.tax.income-tax',           'available' => true],
                ['key' => 'finance.assets',        'label' => 'Asset Register',        'description' => 'Fixed assets, costs and book values',        'route'  => 'finance.assets.index',             'available' => true],
                ['key' => 'finance.depreciation',  'label' => 'Depreciation Report',   'description' => 'Monthly depreciation schedules',             'action' => 'finance.depreciation',             'available' => true],
                ['key' => 'finance.payroll',       'label' => 'Payroll Report',        'description' => 'Payroll periods, gross/net and deductions',  'action' => 'finance.payroll',                  'available' => true],
                ['key' => 'finance.bank_recon',    'label' => 'Bank Reconciliation',   'description' => 'Bank account reconciliation history',        'route'  => 'finance.bank.index',               'available' => true],
                ['key' => 'finance.pl_trend',      'label' => 'P&L Trend',            'description' => '6-month profit & loss trend',               'action' => 'finance.pl_trend',                 'available' => true],
            ],
            'CRM' => [
                ['key' => 'crm.customers',         'label' => 'Customer List',         'description' => 'All customers with balances and activity',   'action' => 'crm.customers',         'available' => true],
                ['key' => 'crm.new_customers',     'label' => 'New Customers',         'description' => 'Customer acquisition trend',                 'action' => 'crm.new_customers',     'available' => true],
                ['key' => 'crm.top_customers',     'label' => 'Top Customers',         'description' => 'Highest-value customers by LTV',             'action' => 'crm.top_customers',     'available' => true],
                ['key' => 'crm.overdue',           'label' => 'Overdue Accounts',      'description' => 'Customers with outstanding credit',          'action' => 'crm.overdue',           'available' => true],
            ],
            'Employees' => [
                ['key' => 'hr.payroll',            'label' => 'Payroll Summary',       'description' => 'Payroll periods with totals',                'action' => 'hr.payroll',            'available' => true],
                ['key' => 'hr.employees',          'label' => 'Employee List',         'description' => 'All employee profiles',                      'route'  => 'payroll.employees.index','available' => true],
                ['key' => 'hr.attendance',         'label' => 'Attendance',            'description' => 'Attendance tracking report',                 'available' => false],
                ['key' => 'hr.leave',              'label' => 'Leave Report',          'description' => 'Employee leave records',                     'available' => false],
            ],
            'AI Insights' => [
                ['key' => 'ai.business_health',    'label' => 'Business Health',       'description' => 'AI-generated health score and recommendations', 'action' => 'ai.business_health', 'available' => true],
                ['key' => 'ai.forecast',           'label' => 'Sales Forecast',        'description' => 'Linear trend-based revenue projection',      'action' => 'ai.forecast',           'available' => true],
                ['key' => 'ai.slow_movers',        'label' => 'Slow Mover Alert',      'description' => 'AI-identified low velocity stock',           'action' => 'ai.slow_movers',        'available' => true],
            ],
        ];
    }

    /**
     * Hub stats for the dashboard header.
     *
     * @return array<string, mixed>
     */
    public function hubStats(string $businessId): array
    {
        $catalog = $this->catalog();
        $total = 0;
        $available = 0;
        $byCat = [];

        foreach ($catalog as $cat => $reports) {
            $catAvail = collect($reports)->where('available', true)->count();
            $byCat[$cat] = ['total' => count($reports), 'available' => $catAvail];
            $total += count($reports);
            $available += $catAvail;
        }

        return [
            'total_reports' => $total,
            'available_reports' => $available,
            'by_category' => $byCat,
        ];
    }

    /**
     * Generate inline report data for the Show page.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function generate(string $key, string $businessId, array $filters = []): array
    {
        $from = Carbon::parse($filters['date_from'] ?? now()->subDays(30)->toDateString())->startOfDay();
        $to = Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay();

        return match ($key) {
            'sales.daily'          => $this->salesDailyReport($businessId, $from, $to),
            'sales.monthly'        => $this->salesMonthlyReport($businessId),
            'sales.by_product'     => $this->salesByProductReport($businessId),
            'sales.by_branch'      => $this->salesByBranchReport($businessId),
            'sales.by_payment'     => $this->salesByPaymentReport($businessId),
            'sales.top_customers'  => $this->topCustomersReport($businessId),
            'sales.returns'        => $this->salesReturnsReport($businessId, $from, $to),
            'sales.slow_moving'    => $this->slowMovingReport($businessId),
            'sales.customer_debts' => $this->customerDebtsReport($businessId),
            'inventory.stock'      => $this->inventoryStockReport($businessId),
            'inventory.low_stock'  => $this->lowStockReport($businessId),
            'inventory.out_of_stock' => $this->outOfStockReport($businessId),
            'inventory.dead_stock' => $this->deadStockReport($businessId),
            'inventory.expired'    => $this->expiredReport($businessId),
            'inventory.movements'  => $this->movementsReport($businessId, $from, $to),
            'inventory.valuation'  => $this->valuationReport($businessId),
            'purchasing.orders'    => $this->purchaseOrdersReport($businessId),
            'purchasing.suppliers' => $this->supplierPerformanceReport($businessId),
            'purchasing.lead_times'=> $this->supplierLeadTimesReport($businessId),
            'purchasing.trend'     => $this->purchaseTrendReport($businessId),
            'finance.depreciation' => $this->depreciationReport($businessId),
            'finance.payroll'      => $this->payrollReport($businessId),
            'finance.pl_trend'     => $this->plTrendReport($businessId),
            'crm.customers'        => $this->crmCustomersReport($businessId),
            'crm.new_customers'    => $this->newCustomersReport($businessId),
            'crm.top_customers'    => $this->topCustomersReport($businessId),
            'crm.overdue'          => $this->customerDebtsReport($businessId),
            'hr.payroll'           => $this->payrollReport($businessId),
            'ai.business_health'   => $this->aiHealthReport($businessId),
            'ai.forecast'          => $this->aiForecastReport($businessId),
            'ai.slow_movers'       => $this->slowMovingReport($businessId),
            default                => ['columns' => [], 'rows' => [], 'summary' => []],
        };
    }

    // ─── Sales ────────────────────────────────────────────────────────────────

    private function salesDailyReport(string $businessId, Carbon $from, Carbon $to): array
    {
        $rows = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('created_at')." as date, count(*) as sales_count, sum(total_amount) as revenue, COALESCE(SUM(si_agg.item_cost), 0) as total_cost")
            ->leftJoin(DB::raw("(SELECT sale_id, SUM(quantity * COALESCE(unit_cost, 0)) as item_cost FROM sale_items GROUP BY sale_id) si_agg"), 'si_agg.sale_id', '=', 'sales.id')
            ->groupByRaw(DateFormatSql::daily('created_at'))
            ->orderByRaw(DateFormatSql::daily('created_at'))
            ->get();

        return [
            'columns' => ['Date', 'Sales Count', 'Revenue', 'Profit'],
            'rows' => $rows->map(fn ($r) => [
                $r->date, (int) $r->sales_count, (float) $r->revenue,
                round((float) $r->revenue - (float) $r->total_cost, 2),
            ])->all(),
            'summary' => [
                'total_revenue' => (float) $rows->sum('revenue'),
                'total_sales'   => (int) $rows->sum('sales_count'),
                'total_profit'  => round((float) $rows->sum('revenue') - (float) $rows->sum('total_cost'), 2),
            ],
        ];
    }

    private function salesMonthlyReport(string $businessId): array
    {
        $rows = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [now()->subMonths(11)->startOfMonth(), now()->endOfMonth()])
            ->selectRaw(DateFormatSql::monthly('created_at')." as month, count(*) as sales_count, sum(total_amount) as revenue, COALESCE(SUM(si_agg.item_cost), 0) as total_cost")
            ->leftJoin(DB::raw("(SELECT sale_id, SUM(quantity * COALESCE(unit_cost, 0)) as item_cost FROM sale_items GROUP BY sale_id) si_agg"), 'si_agg.sale_id', '=', 'sales.id')
            ->groupByRaw(DateFormatSql::monthly('created_at'))
            ->orderByRaw(DateFormatSql::monthly('created_at'))
            ->get();

        return [
            'columns' => ['Month', 'Sales Count', 'Revenue', 'Profit'],
            'rows' => $rows->map(fn ($r) => [
                $r->month, (int) $r->sales_count, (float) $r->revenue,
                round((float) $r->revenue - (float) $r->total_cost, 2),
            ])->all(),
            'summary' => [
                'total_revenue' => (float) $rows->sum('revenue'),
                'total_profit'  => round((float) $rows->sum('revenue') - (float) $rows->sum('total_cost'), 2),
            ],
        ];
    }

    private function salesByProductReport(string $businessId): array
    {
        $products = $this->salesDashboard->topSellingProducts($businessId, 50);

        return [
            'columns' => ['Product', 'Units Sold', 'Revenue'],
            'rows' => array_map(fn ($r) => [$r['name'], (int) $r['quantity_sold'], (float) $r['revenue']], $products),
            'summary' => ['total_revenue' => array_sum(array_column($products, 'revenue'))],
        ];
    }

    private function salesByBranchReport(string $businessId): array
    {
        $branches = $this->salesDashboard->branchPerformance($businessId);

        return [
            'columns' => ['Branch', 'Revenue', '% Change'],
            'rows' => array_map(fn ($r) => [
                $r['name'], (float) $r['revenue'],
                $r['percent_change'] !== null ? $r['percent_change'] . '%' : '—',
            ], $branches),
            'summary' => ['total_revenue' => array_sum(array_column($branches, 'revenue'))],
        ];
    }

    private function salesByPaymentReport(string $businessId): array
    {
        $methods = $this->salesDashboard->paymentMethodBreakdown($businessId);

        return [
            'columns' => ['Payment Method', 'Transactions', 'Amount'],
            'rows' => array_map(fn ($r) => [$r['payment_method'], (int) $r['count'], (float) $r['total']], $methods),
            'summary' => ['total_amount' => array_sum(array_column($methods, 'total'))],
        ];
    }

    private function topCustomersReport(string $businessId): array
    {
        $customers = $this->crmDashboard->topCustomers($businessId, 50);

        return [
            'columns' => ['Customer', 'Total Purchases', 'Outstanding Balance'],
            'rows' => array_map(fn ($r) => [$r['name'], (float) $r['total_purchases'], (float) $r['current_balance']], $customers),
            'summary' => [],
        ];
    }

    private function salesReturnsReport(string $businessId, Carbon $from, Carbon $to): array
    {
        $rows = \App\Domain\Sales\Models\SaleReturn::query()
            ->where('business_id', $businessId)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->with('sale:id,sale_number')
            ->get(['id', 'sale_id', 'total_refund_amount', 'reason', 'created_at']);

        return [
            'columns' => ['Sale Ref', 'Refund Amount', 'Reason', 'Date'],
            'rows' => $rows->map(fn ($r) => [
                $r->sale?->sale_number, (float) $r->total_refund_amount, $r->reason, $r->created_at->toDateString(),
            ])->all(),
            'summary' => ['total_refunded' => (float) $rows->sum('total_refund_amount')],
        ];
    }

    private function slowMovingReport(string $businessId): array
    {
        $since = now()->subDays(30);
        $soldIds = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.created_at', '>=', $since)
            ->pluck('sale_items.product_id')
            ->unique();

        $rows = \App\Domain\Inventory\Models\Inventory::query()
            ->where('business_id', $businessId)
            ->where('quantity', '>', 0)
            ->whereNotIn('product_id', $soldIds)
            ->with('product:id,name,sku')
            ->limit(100)
            ->get();

        return [
            'columns' => ['Product', 'SKU', 'Quantity', 'Days Without Sales'],
            'rows' => $rows->map(fn ($r) => [$r->product?->name, $r->product?->sku, (float) $r->quantity, '30+'])->all(),
            'summary' => ['count' => $rows->count()],
        ];
    }

    private function customerDebtsReport(string $businessId): array
    {
        $rows = Customer::query()
            ->where('business_id', $businessId)
            ->where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->get(['name', 'phone', 'email', 'current_balance', 'credit_limit', 'created_at']);

        return [
            'columns' => ['Customer', 'Phone', 'Outstanding Balance', 'Credit Limit'],
            'rows' => $rows->map(fn ($c) => [$c->name, $c->phone, (float) $c->current_balance, (float) $c->credit_limit])->all(),
            'summary' => ['total_outstanding' => (float) $rows->sum('current_balance'), 'count' => $rows->count()],
        ];
    }

    // ─── Inventory ────────────────────────────────────────────────────────────

    private function inventoryStockReport(string $businessId): array
    {
        $rows = \App\Domain\Inventory\Models\Inventory::query()
            ->where('business_id', $businessId)
            ->with(['product:id,name,sku', 'warehouse:id,name'])
            ->orderBy('product_id')
            ->get();

        return [
            'columns' => ['Product', 'SKU', 'Warehouse', 'Quantity', 'Avg Cost', 'Value'],
            'rows' => $rows->map(fn ($r) => [
                $r->product?->name, $r->product?->sku, $r->warehouse?->name,
                (float) $r->quantity, (float) $r->average_cost,
                round((float) $r->quantity * (float) $r->average_cost, 2),
            ])->all(),
            'summary' => [
                'total_items' => $rows->count(),
                'total_value' => (float) $rows->sum(fn ($r) => $r->quantity * $r->average_cost),
            ],
        ];
    }

    private function lowStockReport(string $businessId): array
    {
        $rows = $this->inventoryDashboard->lowStockProducts($businessId, 200);

        return [
            'columns' => ['Product', 'Current Stock', 'Reorder Level'],
            'rows' => array_map(fn ($r) => [$r['product_name'], (float) $r['quantity'], (float) $r['reorder_level']], $rows),
            'summary' => ['count' => count($rows)],
        ];
    }

    private function outOfStockReport(string $businessId): array
    {
        $rows = \App\Domain\Inventory\Models\Inventory::query()
            ->where('business_id', $businessId)
            ->where('quantity', '<=', 0)
            ->with('product:id,name,sku')
            ->get();

        return [
            'columns' => ['Product', 'SKU'],
            'rows' => $rows->map(fn ($r) => [$r->product?->name, $r->product?->sku])->all(),
            'summary' => ['count' => $rows->count()],
        ];
    }

    private function deadStockReport(string $businessId): array
    {
        $staleSince = now()->subDays(90);
        $movedIds = \App\Domain\Inventory\Models\StockMovement::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $staleSince)
            ->pluck('product_id');

        $rows = \App\Domain\Inventory\Models\Inventory::query()
            ->where('business_id', $businessId)
            ->where('quantity', '>', 0)
            ->whereNotIn('product_id', $movedIds)
            ->with('product:id,name,sku')
            ->get();

        return [
            'columns' => ['Product', 'SKU', 'Quantity', 'Days Idle'],
            'rows' => $rows->map(fn ($r) => [$r->product?->name, $r->product?->sku, (float) $r->quantity, '90+'])->all(),
            'summary' => ['count' => $rows->count()],
        ];
    }

    private function expiredReport(string $businessId): array
    {
        $rows = \App\Domain\Inventory\Models\ProductBatch::query()
            ->where('business_id', $businessId)
            ->where('expiry_date', '<', now())
            ->where('status', '!=', \App\Domain\Inventory\Models\ProductBatch::STATUS_DEPLETED)
            ->with('product:id,name,sku')
            ->get();

        return [
            'columns' => ['Product', 'Batch #', 'Expiry Date', 'Quantity'],
            'rows' => $rows->map(fn ($r) => [$r->product?->name, $r->batch_number, $r->expiry_date?->toDateString(), (float) $r->quantity])->all(),
            'summary' => ['count' => $rows->count()],
        ];
    }

    private function movementsReport(string $businessId, Carbon $from, Carbon $to): array
    {
        $rows = \App\Domain\Inventory\Models\StockMovement::query()
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$from, $to])
            ->with('product:id,name')
            ->latest('created_at')
            ->limit(500)
            ->get();

        return [
            'columns' => ['Product', 'Type', 'Quantity', 'Date'],
            'rows' => $rows->map(fn ($r) => [$r->product?->name, $r->type, (float) $r->quantity, $r->created_at->toDateString()])->all(),
            'summary' => ['count' => $rows->count()],
        ];
    }

    private function valuationReport(string $businessId): array
    {
        $rows = \App\Domain\Inventory\Models\Inventory::query()
            ->where('business_id', $businessId)
            ->with('product:id,name')
            ->selectRaw('product_id, sum(quantity) as total_qty, avg(average_cost) as avg_cost, sum(quantity * average_cost) as value')
            ->groupBy('product_id')
            ->orderByDesc('value')
            ->with('product:id,name')
            ->get();

        return [
            'columns' => ['Product', 'Total Qty', 'Avg Cost', 'Total Value'],
            'rows' => $rows->map(fn ($r) => [$r->product?->name, (float) $r->total_qty, (float) $r->avg_cost, (float) $r->value])->all(),
            'summary' => ['total_value' => (float) $rows->sum('value')],
        ];
    }

    // ─── Purchasing ───────────────────────────────────────────────────────────

    private function purchaseOrdersReport(string $businessId): array
    {
        $orders = $this->purchasingDashboard->recentPurchaseOrders($businessId, 100);

        return [
            'columns' => ['Order #', 'Supplier', 'Status', 'Total', 'Date'],
            'rows' => array_map(fn ($o) => [$o['order_number'], $o['supplier_name'], $o['status'], (float) $o['total_amount'], $o['created_at']], $orders),
            'summary' => [],
        ];
    }

    private function supplierPerformanceReport(string $businessId): array
    {
        $suppliers = $this->purchasingDashboard->topSuppliers($businessId, 50);

        return [
            'columns' => ['Supplier', 'Orders', 'Total Value'],
            'rows' => array_map(fn ($s) => [$s['name'], (int) $s['order_count'], (float) $s['total_value']], $suppliers),
            'summary' => [],
        ];
    }

    private function supplierLeadTimesReport(string $businessId): array
    {
        $rows = $this->purchasingDashboard->supplierLeadTimes($businessId, 50);

        return [
            'columns' => ['Supplier', 'Avg Lead Time (days)'],
            'rows' => array_map(fn ($r) => [$r['supplier_name'], (float) $r['avg_lead_days']], $rows),
            'summary' => [],
        ];
    }

    private function purchaseTrendReport(string $businessId): array
    {
        $trend = $this->purchasingDashboard->trend($businessId);

        return [
            'columns' => ['Week', 'Orders', 'Value'],
            'rows' => array_map(fn ($r) => [$r['week'], (int) $r['count'], (float) $r['value']], $trend),
            'summary' => ['total_value' => array_sum(array_column($trend, 'value'))],
        ];
    }

    // ─── Finance ──────────────────────────────────────────────────────────────

    private function depreciationReport(string $businessId): array
    {
        $rows = \App\Domain\Finance\Models\DepreciationSchedule::query()
            ->join('fixed_assets', 'fixed_assets.id', '=', 'depreciation_schedules.fixed_asset_id')
            ->where('fixed_assets.business_id', $businessId)
            ->select('fixed_assets.asset_name', 'depreciation_schedules.*')
            ->orderBy('depreciation_schedules.period_date', 'desc')
            ->limit(200)
            ->get();

        return [
            'columns' => ['Asset', 'Period', 'Depreciation', 'Acc. Depreciation', 'Book Value', 'Status'],
            'rows' => $rows->map(fn ($r) => [
                $r->asset_name, $r->period_date, (float) $r->depreciation_amount,
                (float) $r->accumulated_depreciation, (float) $r->book_value, $r->status,
            ])->all(),
            'summary' => ['total_depreciation' => (float) $rows->sum('depreciation_amount')],
        ];
    }

    private function payrollReport(string $businessId): array
    {
        $periods = PayrollPeriod::query()
            ->where('business_id', $businessId)
            ->orderByDesc('period_start')
            ->get();

        return [
            'columns' => ['Period', 'Start', 'End', 'Gross', 'Deductions', 'Net', 'Status'],
            'rows' => $periods->map(fn ($p) => [
                $p->period_name, $p->period_start->toDateString(), $p->period_end->toDateString(),
                (float) $p->total_gross, (float) $p->total_deductions, (float) $p->total_net, $p->status,
            ])->all(),
            'summary' => [
                'total_gross' => (float) $periods->sum('total_gross'),
                'total_net' => (float) $periods->sum('total_net'),
            ],
        ];
    }

    private function plTrendReport(string $businessId): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $data = $this->financialStatements->profitAndLoss($businessId, $month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString());
            $trend[] = [$month->format('M Y'), (float) $data['total_revenue'], (float) $data['total_expenses'], (float) $data['net_profit']];
        }

        return [
            'columns' => ['Month', 'Revenue', 'Expenses', 'Net Profit'],
            'rows' => $trend,
            'summary' => [],
            'chart_type' => 'line',
        ];
    }

    // ─── CRM ──────────────────────────────────────────────────────────────────

    private function crmCustomersReport(string $businessId): array
    {
        $rows = Customer::query()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['name', 'phone', 'email', 'current_balance', 'credit_limit', 'created_at']);

        return [
            'columns' => ['Name', 'Phone', 'Email', 'Balance', 'Credit Limit', 'Joined'],
            'rows' => $rows->map(fn ($c) => [$c->name, $c->phone, $c->email, (float) $c->current_balance, (float) $c->credit_limit, $c->created_at->toDateString()])->all(),
            'summary' => ['total_customers' => $rows->count(), 'total_outstanding' => (float) $rows->sum('current_balance')],
        ];
    }

    private function newCustomersReport(string $businessId): array
    {
        $trend = $this->crmDashboard->newCustomersTrend($businessId);

        return [
            'columns' => ['Month', 'New Customers'],
            'rows' => array_map(fn ($r) => [$r['month'], (int) $r['count']], $trend),
            'summary' => ['total_new' => array_sum(array_column($trend, 'count'))],
        ];
    }

    // ─── AI ───────────────────────────────────────────────────────────────────

    private function aiHealthReport(string $businessId): array
    {
        $business = \App\Domain\Business\Models\Business::find($businessId);

        if (! $business) {
            return ['columns' => [], 'rows' => [], 'summary' => []];
        }

        $health = $this->businessHealth->compute($business);

        return [
            'columns' => ['Indicator', 'Value'],
            'rows' => collect($health)->except(['recommendations'])->map(fn ($v, $k) => [ucwords(str_replace('_', ' ', $k)), $v])->values()->all(),
            'summary' => ['recommendations' => $health['recommendations'] ?? []],
        ];
    }

    private function aiForecastReport(string $businessId): array
    {
        $trend = $this->salesDashboard->revenueTrend($businessId);
        $amounts = array_column($trend, 'amount');
        $n = count($amounts);

        if ($n < 3) {
            return ['columns' => ['Date', 'Actual Revenue'], 'rows' => array_map(fn ($r) => [$r['label'], (float) $r['amount']], $trend), 'summary' => ['note' => 'Insufficient data for forecast']];
        }

        $avgX = ($n - 1) / 2;
        $avgY = array_sum($amounts) / $n;
        $num = $den = 0.0;
        foreach ($amounts as $x => $y) {
            $num += ($x - $avgX) * ($y - $avgY);
            $den += ($x - $avgX) ** 2;
        }
        $slope = $den > 0 ? $num / $den : 0.0;
        $lastY = $avgY + $slope * (($n - 1) - $avgX);
        $projected = round(max(0, $lastY) * 30, 2);

        return [
            'columns' => ['Date', 'Actual Revenue'],
            'rows' => array_map(fn ($r) => [$r['label'], (float) $r['amount']], $trend),
            'summary' => ['projected_monthly_revenue' => $projected, 'note' => 'Linear trend projection from last 14 days'],
        ];
    }
}
