<?php

namespace App\Domain\Finance\Services;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Shared\Support\DateFormatSql;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Every report here reads real, existing tables. There is no Sales/POS
 * module yet (confirmed: only tenant-scope `.view` permission
 * placeholders exist for it, no models/controllers/migrations) — so
 * "Sales Reports" from the original spec are deliberately not
 * implemented. `catalog()` lists them as `available: false` with an
 * honest reason rather than rendering fabricated charts. Likewise
 * "Online Users" has no session-presence tracking anywhere in the
 * codebase and is omitted for the same reason.
 */
class FinanceReportService
{
    /**
     * @return array<int, array{key: string, label: string, category: string, available: bool, unavailable_reason: ?string}>
     */
    public function catalog(): array
    {
        $financial = [
            ['key' => 'revenue', 'label' => 'Revenue Report'],
            ['key' => 'payments', 'label' => 'Payment Report'],
            ['key' => 'subscriptions', 'label' => 'Subscription Report'],
            ['key' => 'licenses', 'label' => 'License Report'],
            ['key' => 'refunds', 'label' => 'Refund Report'],
            ['key' => 'commission', 'label' => 'Commission Report'],
            ['key' => 'tax', 'label' => 'Tax Report'],
            ['key' => 'profit', 'label' => 'Profit Report'],
            ['key' => 'cash_flow', 'label' => 'Cash Flow Report'],
            ['key' => 'outstanding_payments', 'label' => 'Outstanding Payments'],
            ['key' => 'failed_payments', 'label' => 'Failed Payments'],
            ['key' => 'gateway_performance', 'label' => 'Gateway Performance'],
        ];

        $business = [
            ['key' => 'business_growth', 'label' => 'Business Growth'],
            ['key' => 'business_revenue', 'label' => 'Business Revenue'],
            ['key' => 'most_active_businesses', 'label' => 'Most Active Businesses'],
            ['key' => 'top_paying_businesses', 'label' => 'Top Paying Businesses'],
            ['key' => 'expired_businesses', 'label' => 'Expired Businesses'],
            ['key' => 'trial_businesses', 'label' => 'Trial Businesses'],
            ['key' => 'subscription_distribution', 'label' => 'Subscription Distribution'],
        ];

        $user = [
            ['key' => 'user_growth', 'label' => 'User Growth'],
            ['key' => 'role_distribution', 'label' => 'Role Distribution'],
            ['key' => 'recent_logins', 'label' => 'Recent Logins'],
        ];

        $inventory = [
            ['key' => 'inventory_value', 'label' => 'Inventory Value'],
            ['key' => 'stock_movements', 'label' => 'Stock Movements'],
            ['key' => 'low_stock', 'label' => 'Low Stock'],
            ['key' => 'expired_products', 'label' => 'Expired Products'],
            ['key' => 'dead_stock', 'label' => 'Dead Stock'],
        ];

        $sales = [
            ['key' => 'daily_sales', 'label' => 'Daily Sales'],
            ['key' => 'top_products', 'label' => 'Top Products'],
            ['key' => 'top_customers', 'label' => 'Top Customers'],
            ['key' => 'sales_by_branch', 'label' => 'Sales by Branch'],
            ['key' => 'sales_by_employee', 'label' => 'Sales by Employee'],
        ];

        $unavailable = 'No Sales/POS module exists yet — this report has no real transactional data to show.';

        return [
            ...$this->tagCategory($financial, 'Financial', true),
            ...$this->tagCategory($business, 'Business', true),
            ...$this->tagCategory($user, 'User', true),
            ...$this->tagCategory($inventory, 'Inventory', true),
            ...$this->tagCategory($sales, 'Sales', false, $unavailable),
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $reports
     * @return array<int, array{key: string, label: string, category: string, available: bool, unavailable_reason: ?string}>
     */
    private function tagCategory(array $reports, string $category, bool $available, ?string $reason = null): array
    {
        return array_map(fn ($report) => [
            ...$report,
            'category' => $category,
            'available' => $available,
            'unavailable_reason' => $reason,
        ], $reports);
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(string $key, array $filters = []): array
    {
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $dateTo = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : Carbon::now()->endOfDay();

        return match ($key) {
            'revenue' => $this->revenueReport($dateFrom, $dateTo),
            'payments' => $this->paymentsReport($dateFrom, $dateTo),
            'subscriptions' => $this->subscriptionsReport(),
            'licenses' => $this->licensesReport(),
            'refunds' => $this->refundsReport($dateFrom, $dateTo),
            'commission' => $this->commissionReport($dateFrom, $dateTo),
            'tax' => $this->taxReport($dateFrom, $dateTo),
            'profit' => $this->profitReport($dateFrom, $dateTo),
            'cash_flow' => $this->cashFlowReport($dateFrom, $dateTo),
            'outstanding_payments' => $this->outstandingPaymentsReport(),
            'failed_payments' => $this->failedPaymentsReport($dateFrom, $dateTo),
            'business_growth' => $this->businessGrowthReport($dateFrom, $dateTo),
            'business_revenue' => $this->businessRevenueReport(),
            'most_active_businesses' => $this->mostActiveBusinessesReport(),
            'top_paying_businesses' => $this->businessRevenueReport(),
            'expired_businesses' => $this->businessesByStatusReport(Business::STATUS_EXPIRED),
            'trial_businesses' => $this->businessesByStatusReport(Business::STATUS_TRIAL),
            'subscription_distribution' => $this->subscriptionDistributionReport(),
            'user_growth' => $this->userGrowthReport($dateFrom, $dateTo),
            'role_distribution' => $this->roleDistributionReport(),
            'recent_logins' => $this->recentLoginsReport(),
            'inventory_value' => $this->inventoryValueReport(),
            'stock_movements' => $this->stockMovementsReport($dateFrom, $dateTo),
            'low_stock' => $this->lowStockReport(),
            'expired_products' => $this->expiredProductsReport(),
            'dead_stock' => $this->deadStockReport(),
            default => ['columns' => [], 'rows' => []],
        };
    }

    private function revenueReport(Carbon $from, Carbon $to): array
    {
        $rows = PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('paid_at')." as date, sum(amount) as total, count(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'columns' => ['Date', 'Transactions', 'Revenue'],
            'rows' => $rows->map(fn ($r) => [$r->date, (int) $r->count, (float) $r->total])->all(),
            'summary' => ['total_revenue' => (float) $rows->sum('total'), 'total_transactions' => (int) $rows->sum('count')],
        ];
    }

    private function paymentsReport(Carbon $from, Carbon $to): array
    {
        $transactions = PaymentTransaction::query()
            ->with(['business', 'gateway'])
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')
            ->limit(500)
            ->get();

        return [
            'columns' => ['Reference', 'Business', 'Gateway', 'Amount', 'Currency', 'Status', 'Method', 'Date'],
            'rows' => $transactions->map(fn (PaymentTransaction $t) => [
                $t->reference_number, $t->business?->name, $t->gateway?->name ?? 'Manual',
                (float) $t->amount, $t->currency, $t->status, $t->payment_method, $t->created_at->toDateTimeString(),
            ])->all(),
        ];
    }

    private function subscriptionsReport(): array
    {
        $rows = Subscription::query()->with(['business', 'plan'])->get();

        return [
            'columns' => ['Business', 'Plan', 'Status', 'Current Period End'],
            'rows' => $rows->map(fn (Subscription $s) => [
                $s->business?->name, $s->plan?->name, $s->status, $s->current_period_end?->toDateString(),
            ])->all(),
        ];
    }

    private function licensesReport(): array
    {
        $rows = \App\Domain\Licensing\Models\License::query()->with('business')->get();

        return [
            'columns' => ['Business', 'License Key', 'Type', 'Status', 'Issued', 'Expires'],
            'rows' => $rows->map(fn ($l) => [
                $l->business?->name, $l->license_key, $l->type, $l->status,
                $l->issued_at?->toDateString(), $l->expires_at?->toDateString(),
            ])->all(),
        ];
    }

    private function refundsReport(Carbon $from, Carbon $to): array
    {
        $rows = PaymentTransaction::query()
            ->with('business')
            ->whereIn('type', [PaymentTransaction::TYPE_REFUND, PaymentTransaction::TYPE_PARTIAL_REFUND])
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')
            ->get();

        return [
            'columns' => ['Reference', 'Business', 'Type', 'Amount', 'Reason', 'Date'],
            'rows' => $rows->map(fn (PaymentTransaction $t) => [
                $t->reference_number, $t->business?->name, $t->type, (float) $t->amount, $t->notes, $t->created_at->toDateString(),
            ])->all(),
            'summary' => ['total_refunded' => (float) $rows->sum('amount')],
        ];
    }

    private function commissionReport(Carbon $from, Carbon $to): array
    {
        $rows = PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('paid_at')." as date, sum(commission_amount) as commission")
            ->groupBy('date')->orderBy('date')->get();

        return [
            'columns' => ['Date', 'Commission'],
            'rows' => $rows->map(fn ($r) => [$r->date, (float) $r->commission])->all(),
            'summary' => ['total_commission' => (float) $rows->sum('commission')],
        ];
    }

    private function taxReport(Carbon $from, Carbon $to): array
    {
        $rows = PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('paid_at')." as date, sum(tax_amount) as tax")
            ->groupBy('date')->orderBy('date')->get();

        return [
            'columns' => ['Date', 'Tax Collected'],
            'rows' => $rows->map(fn ($r) => [$r->date, (float) $r->tax])->all(),
            'summary' => ['total_tax' => (float) $rows->sum('tax')],
        ];
    }

    private function profitReport(Carbon $from, Carbon $to): array
    {
        $rows = PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('paid_at')." as date, sum(amount) as gross, sum(fee_amount) as fees, sum(commission_amount) as commission")
            ->groupBy('date')->orderBy('date')->get();

        return [
            'columns' => ['Date', 'Gross', 'Fees', 'Commission', 'Net Profit'],
            'rows' => $rows->map(fn ($r) => [
                $r->date, (float) $r->gross, (float) $r->fees, (float) $r->commission,
                (float) $r->gross - (float) $r->fees - (float) $r->commission,
            ])->all(),
        ];
    }

    private function cashFlowReport(Carbon $from, Carbon $to): array
    {
        $inflow = PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->whereNotIn('type', [PaymentTransaction::TYPE_REFUND, PaymentTransaction::TYPE_PARTIAL_REFUND])
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('paid_at')." as date, sum(amount) as total")
            ->groupBy('date')->pluck('total', 'date');

        $outflow = PaymentTransaction::query()
            ->whereIn('type', [PaymentTransaction::TYPE_REFUND, PaymentTransaction::TYPE_PARTIAL_REFUND])
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('paid_at')." as date, sum(amount) as total")
            ->groupBy('date')->pluck('total', 'date');

        $dates = $inflow->keys()->merge($outflow->keys())->unique()->sort()->values();

        return [
            'columns' => ['Date', 'Inflow', 'Outflow', 'Net'],
            'rows' => $dates->map(fn ($date) => [
                $date, (float) ($inflow[$date] ?? 0), (float) ($outflow[$date] ?? 0),
                (float) ($inflow[$date] ?? 0) - (float) ($outflow[$date] ?? 0),
            ])->all(),
        ];
    }

    private function outstandingPaymentsReport(): array
    {
        $rows = PaymentTransaction::query()
            ->with('business')
            ->whereIn('status', [PaymentTransaction::STATUS_PENDING, PaymentTransaction::STATUS_PROCESSING])
            ->latest('created_at')->get();

        return [
            'columns' => ['Reference', 'Business', 'Amount', 'Status', 'Created'],
            'rows' => $rows->map(fn (PaymentTransaction $t) => [
                $t->reference_number, $t->business?->name, (float) $t->amount, $t->status, $t->created_at->toDateString(),
            ])->all(),
            'summary' => ['total_outstanding' => (float) $rows->sum('amount')],
        ];
    }

    private function failedPaymentsReport(Carbon $from, Carbon $to): array
    {
        $rows = PaymentTransaction::query()
            ->with(['business', 'gateway'])
            ->where('status', PaymentTransaction::STATUS_FAILED)
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')->get();

        return [
            'columns' => ['Reference', 'Business', 'Gateway', 'Amount', 'Date'],
            'rows' => $rows->map(fn (PaymentTransaction $t) => [
                $t->reference_number, $t->business?->name, $t->gateway?->name, (float) $t->amount, $t->created_at->toDateString(),
            ])->all(),
        ];
    }

    private function businessGrowthReport(Carbon $from, Carbon $to): array
    {
        $rows = Business::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('created_at')." as date, count(*) as count")
            ->groupBy('date')->orderBy('date')->get();

        return [
            'columns' => ['Date', 'New Businesses'],
            'rows' => $rows->map(fn ($r) => [$r->date, (int) $r->count])->all(),
        ];
    }

    private function businessRevenueReport(): array
    {
        $rows = PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->selectRaw('business_id, sum(amount) as total, count(*) as count')
            ->groupBy('business_id')->orderByDesc('total')
            ->with('business:id,name')->limit(100)->get();

        return [
            'columns' => ['Business', 'Total Paid', 'Transactions'],
            'rows' => $rows->map(fn ($r) => [$r->business?->name, (float) $r->total, (int) $r->count])->all(),
        ];
    }

    private function mostActiveBusinessesReport(): array
    {
        $rows = PaymentTransaction::query()
            ->selectRaw('business_id, count(*) as count')
            ->groupBy('business_id')->orderByDesc('count')
            ->with('business:id,name')->limit(50)->get();

        return [
            'columns' => ['Business', 'Transaction Count'],
            'rows' => $rows->map(fn ($r) => [$r->business?->name, (int) $r->count])->all(),
        ];
    }

    private function businessesByStatusReport(string $status): array
    {
        $rows = Business::query()->where('status', $status)->with('owner')->get();

        return [
            'columns' => ['Business', 'Owner', 'Created', 'Status'],
            'rows' => $rows->map(fn (Business $b) => [$b->name, $b->owner?->name, $b->created_at->toDateString(), $b->status])->all(),
        ];
    }

    private function subscriptionDistributionReport(): array
    {
        $rows = Subscription::query()
            ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->selectRaw('subscription_plans.name as plan_name, count(*) as count')
            ->groupBy('subscription_plans.name')->get();

        return [
            'columns' => ['Plan', 'Subscribers'],
            'rows' => $rows->map(fn ($r) => [$r->plan_name, (int) $r->count])->all(),
        ];
    }

    private function userGrowthReport(Carbon $from, Carbon $to): array
    {
        $rows = User::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw(DateFormatSql::daily('created_at')." as date, count(*) as count")
            ->groupBy('date')->orderBy('date')->get();

        return [
            'columns' => ['Date', 'New Users'],
            'rows' => $rows->map(fn ($r) => [$r->date, (int) $r->count])->all(),
        ];
    }

    private function roleDistributionReport(): array
    {
        // Counted through the pivot, not the legacy `users.role_id`
        // column. A user may now hold several roles and is counted once
        // under each, so the totals sum to more than the number of users
        // — which is the correct reading of "users per role".
        $rows = User::query()
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->selectRaw('roles.name as role_name, count(*) as count')
            ->groupBy('roles.name')->orderByDesc('count')->get();

        return [
            'columns' => ['Role', 'Users'],
            'rows' => $rows->map(fn ($r) => [$r->role_name, (int) $r->count])->all(),
        ];
    }

    private function recentLoginsReport(): array
    {
        $rows = User::query()->whereNotNull('last_login_at')->with('business')->latest('last_login_at')->limit(100)->get();

        return [
            'columns' => ['Name', 'Business', 'Last Login'],
            'rows' => $rows->map(fn (User $u) => [$u->name, $u->business?->name, $u->last_login_at?->toDateTimeString()])->all(),
        ];
    }

    private function inventoryValueReport(): array
    {
        $rows = Inventory::query()
            ->selectRaw('business_id, sum(quantity * average_cost) as value')
            ->groupBy('business_id')->orderByDesc('value')
            ->with('business:id,name')->limit(100)->get();

        return [
            'columns' => ['Business', 'Inventory Value'],
            'rows' => $rows->map(fn ($r) => [$r->business?->name, (float) $r->value])->all(),
        ];
    }

    private function stockMovementsReport(Carbon $from, Carbon $to): array
    {
        $rows = StockMovement::query()
            
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')->get();

        return [
            'columns' => ['Movement Type', 'Count'],
            'rows' => $rows->map(fn ($r) => [$r->type, (int) $r->count])->all(),
        ];
    }

    private function lowStockReport(): array
    {
        $rows = Inventory::query()
            ->where('quantity', '>', 0)
            ->where('quantity', '<', Inventory::LOW_STOCK_THRESHOLD)
            ->with(['product:id,name', 'business:id,name'])
            ->limit(200)->get();

        return [
            'columns' => ['Business', 'Product', 'Quantity', 'Reorder Level'],
            'rows' => $rows->map(fn ($r) => [$r->business?->name, $r->product?->name, (float) $r->quantity, (float) $r->reorder_level])->all(),
        ];
    }

    private function expiredProductsReport(): array
    {
        $rows = ProductBatch::query()
            
            ->where('expiry_date', '<', now())
            ->where('status', '!=', ProductBatch::STATUS_DEPLETED)
            ->with(['product:id,name', 'business:id,name'])
            ->limit(200)->get();

        return [
            'columns' => ['Business', 'Product', 'Batch', 'Expiry Date', 'Quantity'],
            'rows' => $rows->map(fn ($r) => [
                $r->business?->name, $r->product?->name, $r->batch_number, $r->expiry_date?->toDateString(), (float) $r->quantity,
            ])->all(),
        ];
    }

    private function deadStockReport(): array
    {
        $staleSince = now()->subDays(90);

        $movedProductIds = StockMovement::query()
            
            ->where('created_at', '>=', $staleSince)
            ->pluck('product_id');

        $rows = Inventory::query()
            
            ->where('quantity', '>', 0)
            ->whereNotIn('product_id', $movedProductIds)
            ->with(['product:id,name', 'business:id,name'])
            ->limit(200)->get();

        return [
            'columns' => ['Business', 'Product', 'Quantity', 'Days Idle'],
            'rows' => $rows->map(fn ($r) => [$r->business?->name, $r->product?->name, (float) $r->quantity, '90+'])->all(),
        ];
    }
}
