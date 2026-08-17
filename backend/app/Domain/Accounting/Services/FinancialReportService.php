<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\Income;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SalePayment;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Shared\Support\DateFormatSql;
use Illuminate\Support\Carbon;

/**
 * Every figure here is computed live from real rows — Sales revenue/COGS
 * is pulled straight from the Sales module's own `sales`/`sale_items`
 * tables and combined with this module's real Expense/Income rows; it is
 * never re-entered by hand. "Cash Balance" / "Bank Balance" are a real
 * running total of cash- and bank_transfer-method transactions, not a
 * bank-reconciled ledger — that level of bookkeeping (Chart of Accounts /
 * General Ledger) is intentionally out of scope for this phase.
 */
class FinancialReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $salesRevenue = $this->salesRevenue($businessId, $monthStart, $monthEnd);
        $salesProfit = $this->salesProfit($businessId, $monthStart, $monthEnd);
        $otherIncome = $this->otherIncome($businessId, $monthStart, $monthEnd);
        $operatingExpenses = $this->operatingExpenses($businessId, $monthStart, $monthEnd);
        $taxCollected = $this->taxCollected($businessId, $monthStart, $monthEnd);
        $refunds = $this->refunds($businessId, $monthStart, $monthEnd);

        $totalRevenue = $salesRevenue + $otherIncome - $refunds;
        $grossProfit = $salesProfit + $otherIncome - $refunds;
        $netProfit = $grossProfit - $operatingExpenses;

        $today = Carbon::today();

        return [
            'cash_balance' => $this->balanceFor($businessId, 'cash'),
            'bank_balance' => $this->balanceFor($businessId, 'bank_transfer'),
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($operatingExpenses, 2),
            'today_expenses' => round($this->operatingExpenses($businessId, $today, $today), 2),
            'gross_profit' => round($grossProfit, 2),
            'net_profit' => round($netProfit, 2),
            'refunds_this_month' => round($refunds, 2),
            'outstanding_debts' => (float) Customer::query()->where('business_id', $businessId)->sum('current_balance'),
            'accounts_payable' => $this->accountsPayable($businessId),
            'tax_collected' => round($taxCollected, 2),
            'pending_expenses_count' => Expense::query()->where('business_id', $businessId)->where('status', Expense::STATUS_PENDING)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profitAndLoss(string $businessId, Carbon $from, Carbon $to): array
    {
        $salesRevenue = $this->salesRevenue($businessId, $from, $to);
        $salesProfit = $this->salesProfit($businessId, $from, $to);
        $cogs = $this->cogs($businessId, $from, $to);
        $otherIncome = $this->otherIncome($businessId, $from, $to);
        $refunds = $this->refunds($businessId, $from, $to);
        $expensesByCategory = $this->expensesByCategory($businessId, $from, $to);
        $totalExpenses = array_sum(array_column($expensesByCategory, 'total'));

        $grossProfit = $salesProfit + $otherIncome - $refunds;
        $netProfit = $grossProfit - $totalExpenses;

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'sales_revenue' => round($salesRevenue, 2),
            'cost_of_goods_sold' => round($cogs, 2),
            'other_income' => round($otherIncome, 2),
            'refunds' => round($refunds, 2),
            'total_revenue' => round($salesRevenue + $otherIncome - $refunds, 2),
            'gross_profit' => round($grossProfit, 2),
            'expenses_by_category' => $expensesByCategory,
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
        ];
    }

    /**
     * @return array<int, array{label: string, revenue: float, expenses: float, profit: float}>
     */
    public function profitTrend(string $businessId): array
    {
        $since = Carbon::now()->subDays(13)->startOfDay();

        $salesByDay = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('created_at', '>=', $since)
            ->selectRaw(DateFormatSql::daily('created_at')." as day, sum(total_amount) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $expensesByDay = Expense::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [Expense::STATUS_APPROVED, Expense::STATUS_PAID])
            ->where('expense_date', '>=', $since->toDateString())
            ->selectRaw('expense_date as day, sum(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $trend = [];

        for ($date = $since->copy(); $date->lte(Carbon::now()); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $revenue = (float) ($salesByDay[$key] ?? 0);
            $expenses = (float) ($expensesByDay[$key] ?? 0);

            $trend[] = [
                'label' => $date->format('M j'),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'profit' => $revenue - $expenses,
            ];
        }

        return $trend;
    }

    /**
     * @return array<int, array{category: string, total: float}>
     */
    private function expensesByCategory(string $businessId, Carbon $from, Carbon $to): array
    {
        return Expense::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [Expense::STATUS_APPROVED, Expense::STATUS_PAID])
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->with('category:id,name')
            ->get()
            ->groupBy(fn (Expense $expense) => $expense->category?->name ?? 'Uncategorized')
            ->map(fn ($group, $name) => ['category' => $name, 'total' => (float) $group->sum('amount')])
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function salesRevenue(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');
    }

    private function salesRevenueExcludingTax(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->sum('subtotal');
    }

    private function cogs(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) \App\Domain\Sales\Models\SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.created_at', [$from, $to])
            ->whereNotNull('sale_items.unit_cost')
            ->selectRaw('SUM(sale_items.unit_cost * sale_items.quantity) as total')
            ->value('total') ?? 0.0;
    }

    private function salesProfit(string $businessId, Carbon $from, Carbon $to): float
    {
        return $this->salesRevenueExcludingTax($businessId, $from, $to) - $this->cogs($businessId, $from, $to);
    }

    private function otherIncome(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) Income::query()
            ->where('business_id', $businessId)
            ->whereBetween('income_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    private function operatingExpenses(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) Expense::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [Expense::STATUS_APPROVED, Expense::STATUS_PAID])
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    private function taxCollected(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->sum('tax_amount');
    }

    private function accountsPayable(string $businessId): float
    {
        return (float) Expense::query()
            ->where('business_id', $businessId)
            ->where('status', Expense::STATUS_APPROVED)
            ->sum('amount');
    }

    /**
     * Real approved refunds — the one place revenue/profit/cash get
     * reduced for returns. There's no separate "refund expense" journal
     * entry; this reads SaleReturn directly so the figure can never
     * drift from the real return records.
     */
    private function refunds(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) SaleReturn::query()
            ->where('business_id', $businessId)
            ->where('status', SaleReturn::STATUS_APPROVED)
            ->whereBetween('approved_at', [$from, $to])
            ->sum('refund_amount');
    }

    /**
     * Real running balance for a single payment method: every real
     * inflow (sale payments, other income) minus every real paid
     * outflow (paid expenses, refunds issued via that method), all-time.
     */
    private function balanceFor(string $businessId, string $paymentMethod): float
    {
        $salesIn = (float) SalePayment::query()
            ->where('business_id', $businessId)
            ->where('payment_method', $paymentMethod)
            ->sum('amount');

        $otherIn = (float) Income::query()
            ->where('business_id', $businessId)
            ->where('payment_method', $paymentMethod)
            ->sum('amount');

        $out = (float) Expense::query()
            ->where('business_id', $businessId)
            ->where('payment_method', $paymentMethod)
            ->where('status', Expense::STATUS_PAID)
            ->sum('amount');

        $out += (float) SaleReturn::query()
            ->where('business_id', $businessId)
            ->where('refund_method', $paymentMethod)
            ->where('status', SaleReturn::STATUS_APPROVED)
            ->sum('refund_amount');

        return round($salesIn + $otherIn - $out, 2);
    }
}
