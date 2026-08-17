<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\DepreciationSchedule;
use App\Domain\Finance\Models\TaxConfiguration;
use Illuminate\Support\Carbon;

class FinanceDashboardService
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
        private readonly ChartOfAccountsService $accounts,
        private readonly FinancialStatementService $statements,
        private readonly BudgetService $budgetService,
    ) {}

    /**
     * GL-derived KPI summary for the Finance Dashboard. Returns null when the
     * business has no seeded Chart of Accounts (pre-Phase-1 businesses that
     * haven't run the backfill commands yet).
     *
     * @return array<string, mixed>|null
     */
    public function summary(string $businessId): ?array
    {
        if (! Account::query()->where('business_id', $businessId)->where('is_system_default', true)->exists()) {
            return null;
        }

        try {
            $cash = $this->accounts->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_CASH);
            $bank = $this->accounts->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_BANK);
            $ar = $this->accounts->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);
            $ap = $this->accounts->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);

            $cashBalance = $this->ledger->accountBalance($cash);
            $bankBalance = $this->ledger->accountBalance($bank);
            $arBalance = $this->ledger->accountBalance($ar);
            $apBalance = $this->ledger->accountBalance($ap);

            $liquidAssets = bcadd(bcadd($cashBalance, $bankBalance, 2), $arBalance, 2);
            $workingCapital = bcsub($liquidAssets, $apBalance, 2);

            return [
                'cash' => ['value' => (float) $cashBalance],
                'bank' => ['value' => (float) $bankBalance],
                'accounts_receivable' => ['value' => (float) $arBalance],
                'accounts_payable' => ['value' => (float) $apBalance],
                'working_capital' => ['value' => (float) $workingCapital],
                'pl_trend' => $this->buildPlTrend($businessId),
                'alerts' => $this->buildAlerts($businessId, $cashBalance, $bankBalance),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns smart alert strings for the Finance Dashboard banner.
     * Silently swallows errors so the dashboard always renders even when data is partial.
     *
     * @return array<int, array{type: string, message: string}>
     */
    private function buildAlerts(string $businessId, string $cashBalance, string $bankBalance): array
    {
        $alerts = [];

        try {
            // Alert: cash position below 30-day average operating expense
            $monthStart = Carbon::now()->subDays(30)->toDateString();
            $monthEnd = now()->toDateString();
            $pl = $this->statements->profitAndLoss($businessId, $monthStart, $monthEnd);
            $thirtyDayExpenses = (string) $pl['total_expenses'];

            if (bccomp($thirtyDayExpenses, '0', 2) > 0) {
                $totalLiquid = bcadd($cashBalance, $bankBalance, 2);
                if (bccomp($totalLiquid, $thirtyDayExpenses, 2) < 0) {
                    $alerts[] = [
                        'type' => 'warning',
                        'message' => "Low cash: liquid assets ({$totalLiquid}) are below your 30-day operating expenses ({$thirtyDayExpenses}).",
                    ];
                }
            }
        } catch (\Throwable) {
        }

        try {
            // Alert: active budget with a line over 15% variance
            $activeBudget = $this->budgetService->activeBudget($businessId, (int) now()->format('Y'));

            if ($activeBudget) {
                $vsActual = $this->budgetService->budgetVsActual($activeBudget);
                $overBudgetCount = 0;
                foreach ($vsActual as $row) {
                    if ($row['variance_pct'] !== null && bccomp(ltrim((string) $row['variance_pct'], '-'), '15', 2) >= 0 && bccomp((string) $row['variance'], '0', 2) < 0) {
                        $overBudgetCount++;
                    }
                }

                if ($overBudgetCount > 0) {
                    $alerts[] = [
                        'type' => 'warning',
                        'message' => "{$overBudgetCount} budget line(s) are more than 15% over budget in \"{$activeBudget->name}\".",
                    ];
                }
            }
        } catch (\Throwable) {
        }

        try {
            // Alert: assets with pending depreciation due this month or earlier
            $pendingDepreciation = DepreciationSchedule::query()
                ->join('fixed_assets', 'fixed_assets.id', '=', 'depreciation_schedules.fixed_asset_id')
                ->where('fixed_assets.business_id', $businessId)
                ->where('depreciation_schedules.status', 'pending')
                ->where('depreciation_schedules.period_date', '<=', now()->format('Y-m-01'))
                ->count();

            if ($pendingDepreciation > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'message' => "{$pendingDepreciation} depreciation entry(ies) are pending — go to Assets to post them.",
                ];
            }
        } catch (\Throwable) {
        }

        try {
            // Alert: tax return due within 14 days
            $taxPeriodEnd = TaxConfiguration::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->exists();

            if ($taxPeriodEnd) {
                $daysUntilMonthEnd = now()->diffInDays(now()->endOfMonth());
                if ($daysUntilMonthEnd <= 14) {
                    $alerts[] = [
                        'type' => 'info',
                        'message' => "Month end is in {$daysUntilMonthEnd} day(s) — remember to file your tax return if required.",
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return $alerts;
    }

    /**
     * @return array<int, array{label: string, revenue: float, expenses: float, profit: float}>
     */
    private function buildPlTrend(string $businessId): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $from = $month->startOfMonth()->toDateString();
            $to = $month->endOfMonth()->toDateString();

            $data = $this->statements->profitAndLoss($businessId, $from, $to);

            $trend[] = [
                'label' => $month->format('M'),
                'revenue' => (float) $data['total_revenue'],
                'expenses' => (float) $data['total_expenses'],
                'profit' => (float) $data['net_profit'],
            ];
        }

        return $trend;
    }
}
