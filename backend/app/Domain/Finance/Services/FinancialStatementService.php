<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Account;
use Illuminate\Support\Carbon;

/**
 * GL-derived financial statements. Distinct from, and does not replace,
 * Accounting\FinancialReportService — that service stays the fast,
 * live-computed P&L used on the existing Accounting dashboard. This one is
 * the authoritative, audit-traceable report once the ledger is in use;
 * small divergence between the two (rounding/timing) is expected and is
 * surfaced to the user rather than hidden.
 */
class FinancialStatementService
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    /**
     * @return array{from_date: ?string, to_date: ?string, revenue_accounts: array<int, array{account: Account, amount: string}>, total_revenue: string, expense_accounts: array<int, array{account: Account, amount: string}>, total_expenses: string, net_profit: string}
     */
    public function profitAndLoss(string $businessId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $revenueAccounts = $this->activityByType($businessId, Account::TYPE_INCOME, $fromDate, $toDate);
        $expenseAccounts = $this->activityByType($businessId, Account::TYPE_EXPENSE, $fromDate, $toDate);

        $totalRevenue = array_reduce($revenueAccounts, fn ($carry, $row) => bcadd($carry, $row['amount'], 2), '0.00');
        $totalExpenses = array_reduce($expenseAccounts, fn ($carry, $row) => bcadd($carry, $row['amount'], 2), '0.00');

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'revenue_accounts' => $revenueAccounts,
            'total_revenue' => $totalRevenue,
            'expense_accounts' => $expenseAccounts,
            'total_expenses' => $totalExpenses,
            'net_profit' => bcsub($totalRevenue, $totalExpenses, 2),
        ];
    }

    /**
     * @return array{as_of_date: ?string, assets: array<int, array{account: Account, balance: string}>, total_assets: string, liabilities: array<int, array{account: Account, balance: string}>, total_liabilities: string, equity: array<int, array{name: string, balance: string}>, total_equity: string, is_balanced: bool}
     */
    public function balanceSheet(string $businessId, ?string $asOfDate = null): array
    {
        $assets = $this->balanceByType($businessId, Account::TYPE_ASSET, $asOfDate);
        $liabilities = $this->balanceByType($businessId, Account::TYPE_LIABILITY, $asOfDate);
        $equityAccounts = $this->balanceByType($businessId, Account::TYPE_EQUITY, $asOfDate);

        $totalAssets = array_reduce($assets, fn ($carry, $row) => bcadd($carry, $row['balance'], 2), '0.00');
        $totalLiabilities = array_reduce($liabilities, fn ($carry, $row) => bcadd($carry, $row['balance'], 2), '0.00');

        // Income/expense accounts are never closed to equity in Phase 1, so
        // their cumulative net is surfaced here as "Retained Earnings" —
        // without it the balance sheet equation would not hold.
        $pAndL = $this->profitAndLoss($businessId, null, $asOfDate);

        $equity = array_map(
            fn (array $row) => ['name' => $row['account']->name, 'balance' => $row['balance']],
            $equityAccounts,
        );
        $equity[] = ['name' => 'Retained Earnings (Current)', 'balance' => $pAndL['net_profit']];

        $totalEquity = array_reduce($equity, fn ($carry, $row) => bcadd($carry, $row['balance'], 2), '0.00');

        return [
            'as_of_date' => $asOfDate,
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equity,
            'total_equity' => $totalEquity,
            'is_balanced' => bccomp($totalAssets, bcadd($totalLiabilities, $totalEquity, 2), 2) === 0,
        ];
    }

    /**
     * Indirect-method cash flow for the period. Phase 1 only models
     * operating-activity drivers (net income plus the working-capital
     * accounts AutoPostingService actually touches: AR, Inventory, AP);
     * investing/financing activities are zeroed pending a Fixed Assets /
     * Loans phase. "actual_change_in_cash" is the real cash+bank delta and
     * is reported alongside the computed figure as a sanity check — they
     * can diverge slightly around an Opening Balance entry, which moves
     * cash directly against equity rather than through the period's income.
     *
     * @return array<string, mixed>
     */
    public function cashFlowStatement(string $businessId, string $fromDate, string $toDate): array
    {
        $netIncome = $this->profitAndLoss($businessId, $fromDate, $toDate)['net_profit'];

        $priorDate = Carbon::parse($fromDate)->subDay()->toDateString();

        $accountsReceivable = $this->chartOfAccountsService->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);
        $inventory = $this->chartOfAccountsService->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_INVENTORY);
        $accountsPayable = $this->chartOfAccountsService->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);
        $cash = $this->chartOfAccountsService->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_CASH);
        $bank = $this->chartOfAccountsService->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_BANK);

        $arChange = bcsub($this->ledger->accountBalance($accountsReceivable, $toDate), $this->ledger->accountBalance($accountsReceivable, $priorDate), 2);
        $inventoryChange = bcsub($this->ledger->accountBalance($inventory, $toDate), $this->ledger->accountBalance($inventory, $priorDate), 2);
        $apChange = bcsub($this->ledger->accountBalance($accountsPayable, $toDate), $this->ledger->accountBalance($accountsPayable, $priorDate), 2);

        // A rise in an asset (AR/Inventory) is a use of cash; a rise in a
        // liability (AP) is a source of cash.
        $workingCapitalEffect = bcsub($apChange, bcadd($arChange, $inventoryChange, 2), 2);
        $operatingCashFlow = bcadd($netIncome, $workingCapitalEffect, 2);

        $cashAtStart = bcadd($this->ledger->accountBalance($cash, $priorDate), $this->ledger->accountBalance($bank, $priorDate), 2);
        $cashAtEnd = bcadd($this->ledger->accountBalance($cash, $toDate), $this->ledger->accountBalance($bank, $toDate), 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'net_income' => $netIncome,
            'changes_in_working_capital' => [
                'accounts_receivable' => bcmul($arChange, '-1', 2),
                'inventory' => bcmul($inventoryChange, '-1', 2),
                'accounts_payable' => $apChange,
            ],
            'net_cash_from_operating_activities' => $operatingCashFlow,
            'net_cash_from_investing_activities' => '0.00',
            'net_cash_from_financing_activities' => '0.00',
            'net_change_in_cash' => $operatingCashFlow,
            'cash_at_start' => $cashAtStart,
            'cash_at_end' => $cashAtEnd,
            'actual_change_in_cash' => bcsub($cashAtEnd, $cashAtStart, 2),
        ];
    }

    /**
     * @return array<int, array{account: Account, amount: string}>
     */
    private function activityByType(string $businessId, string $type, ?string $fromDate, ?string $toDate): array
    {
        return Account::query()
            ->where('business_id', $businessId)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => ['account' => $account, 'amount' => $this->ledger->accountActivity($account, $fromDate, $toDate)])
            ->filter(fn (array $row) => bccomp($row['amount'], '0', 2) !== 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{account: Account, balance: string}>
     */
    private function balanceByType(string $businessId, string $type, ?string $asOfDate): array
    {
        return Account::query()
            ->where('business_id', $businessId)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => ['account' => $account, 'balance' => $this->ledger->accountBalance($account, $asOfDate)])
            ->filter(fn (array $row) => bccomp($row['balance'], '0', 2) !== 0)
            ->values()
            ->all();
    }
}
