<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Exceptions\LockedPeriodException;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Models\PeriodClosingEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialPeriodService
{
    public function __construct(
        private readonly ChartOfAccountsService $accounts,
        private readonly GeneralLedgerService $ledger,
        private readonly JournalPostingService $posting,
    ) {}

    public function seedDefaultPeriods(string $businessId, int $fiscalYear): void
    {
        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($fiscalYear, $month, 1);
            $end = $start->copy()->endOfMonth();

            FinancialPeriod::query()->firstOrCreate(
                ['business_id' => $businessId, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString()],
                [
                    'fiscal_year' => $fiscalYear,
                    'period_name' => $start->format('F Y'),
                    'status' => FinancialPeriod::STATUS_OPEN,
                    'is_year_end' => $month === 12,
                ],
            );
        }
    }

    public function findByDate(string $businessId, string $date): ?FinancialPeriod
    {
        return FinancialPeriod::query()
            ->where('business_id', $businessId)
            ->where('period_start', '<=', $date)
            ->where('period_end', '>=', $date)
            ->first();
    }

    public function assertPostingAllowed(string $businessId, string $entryDate): void
    {
        $period = $this->findByDate($businessId, $entryDate);

        if ($period && ! $period->isOpen()) {
            throw LockedPeriodException::forPeriod($period);
        }
    }

    public function lock(FinancialPeriod $period, string $lockedBy): FinancialPeriod
    {
        if (! $period->isOpen()) {
            throw new \RuntimeException("Period '{$period->period_name}' is already {$period->status}.");
        }

        $period->update([
            'status' => FinancialPeriod::STATUS_LOCKED,
            'locked_by' => $lockedBy,
            'locked_at' => now(),
        ]);

        return $period->refresh();
    }

    public function close(FinancialPeriod $period, string $closedBy): FinancialPeriod
    {
        if ($period->isClosed()) {
            throw new \RuntimeException("Period '{$period->period_name}' is already closed.");
        }

        return DB::transaction(function () use ($period, $closedBy) {
            $businessId = $period->business_id;
            $periodEnd = $period->period_end->toDateString();

            // Lock it first if it was open
            if ($period->isOpen()) {
                $period->update(['status' => FinancialPeriod::STATUS_LOCKED, 'locked_by' => $closedBy, 'locked_at' => now()]);
            }

            try {
                $incomeSummaryAccount = $this->accounts->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_INCOME_SUMMARY);
                $retainedEarningsAccount = $this->accounts->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_RETAINED_EARNINGS);
            } catch (\Throwable) {
                // Accounts not seeded — skip closing entries
                $period->update(['status' => FinancialPeriod::STATUS_CLOSED, 'closed_by' => $closedBy, 'closed_at' => now()]);

                return $period->refresh();
            }

            // Step 1: Close all income accounts → Income Summary
            $incomeAccounts = Account::query()
                ->where('business_id', $businessId)
                ->where('type', Account::TYPE_INCOME)
                ->where('code', '!=', $incomeSummaryAccount->code)
                ->get();

            $incomeLines = [];
            $totalIncome = '0.00';

            foreach ($incomeAccounts as $account) {
                $balance = $this->ledger->accountBalance($account, $periodEnd);
                if (bccomp($balance, '0', 2) === 0) {
                    continue;
                }
                // Income has credit normal balance; close by debiting
                $incomeLines[] = [
                    'account_id' => $account->id,
                    'debit' => $balance,
                    'credit' => '0',
                    'description' => "Close {$account->name}",
                ];
                $totalIncome = bcadd($totalIncome, $balance, 2);
            }

            if (! empty($incomeLines) && bccomp($totalIncome, '0', 2) !== 0) {
                $incomeLines[] = [
                    'account_id' => $incomeSummaryAccount->id,
                    'debit' => '0',
                    'credit' => $totalIncome,
                    'description' => 'Close income accounts to Income Summary',
                ];

                $je = $this->posting->postImmediately($businessId, [
                    'entry_date' => $periodEnd,
                    'type' => JournalEntry::TYPE_MANUAL,
                    'description' => "Year-end closing — income to Income Summary ({$period->period_name})",
                ], $incomeLines, $closedBy, true);

                PeriodClosingEntry::create([
                    'business_id' => $businessId,
                    'financial_period_id' => $period->id,
                    'closing_journal_entry_id' => $je->id,
                    'closing_type' => PeriodClosingEntry::TYPE_INCOME_SUMMARY,
                    'posted_by' => $closedBy,
                ]);
            }

            // Step 2: Close all expense accounts → Income Summary
            $expenseAccounts = Account::query()
                ->where('business_id', $businessId)
                ->where('type', Account::TYPE_EXPENSE)
                ->get();

            $expenseLines = [];
            $totalExpenses = '0.00';

            foreach ($expenseAccounts as $account) {
                $balance = $this->ledger->accountBalance($account, $periodEnd);
                if (bccomp($balance, '0', 2) === 0) {
                    continue;
                }
                // Expense has debit normal balance; close by crediting
                $expenseLines[] = [
                    'account_id' => $account->id,
                    'debit' => '0',
                    'credit' => $balance,
                    'description' => "Close {$account->name}",
                ];
                $totalExpenses = bcadd($totalExpenses, $balance, 2);
            }

            if (! empty($expenseLines) && bccomp($totalExpenses, '0', 2) !== 0) {
                $expenseLines[] = [
                    'account_id' => $incomeSummaryAccount->id,
                    'debit' => $totalExpenses,
                    'credit' => '0',
                    'description' => 'Close expense accounts to Income Summary',
                ];

                $je2 = $this->posting->postImmediately($businessId, [
                    'entry_date' => $periodEnd,
                    'type' => JournalEntry::TYPE_MANUAL,
                    'description' => "Year-end closing — expenses to Income Summary ({$period->period_name})",
                ], $expenseLines, $closedBy, true);

                PeriodClosingEntry::create([
                    'business_id' => $businessId,
                    'financial_period_id' => $period->id,
                    'closing_journal_entry_id' => $je2->id,
                    'closing_type' => PeriodClosingEntry::TYPE_INCOME_SUMMARY,
                    'posted_by' => $closedBy,
                ]);
            }

            // Step 3: Transfer Income Summary net → Retained Earnings
            $netIncomeSummary = $this->ledger->accountBalance($incomeSummaryAccount);
            if (bccomp($netIncomeSummary, '0', 2) !== 0) {
                $isCredit = bccomp($netIncomeSummary, '0', 2) > 0;

                $je3 = $this->posting->postImmediately($businessId, [
                    'entry_date' => $periodEnd,
                    'type' => JournalEntry::TYPE_MANUAL,
                    'description' => "Year-end closing — Income Summary to Retained Earnings ({$period->period_name})",
                ], [
                    [
                        'account_id' => $incomeSummaryAccount->id,
                        'debit' => $isCredit ? $netIncomeSummary : '0',
                        'credit' => $isCredit ? '0' : ltrim($netIncomeSummary, '-'),
                        'description' => 'Close Income Summary',
                    ],
                    [
                        'account_id' => $retainedEarningsAccount->id,
                        'debit' => $isCredit ? '0' : ltrim($netIncomeSummary, '-'),
                        'credit' => $isCredit ? $netIncomeSummary : '0',
                        'description' => 'Transfer net income to Retained Earnings',
                    ],
                ], $closedBy, true);

                PeriodClosingEntry::create([
                    'business_id' => $businessId,
                    'financial_period_id' => $period->id,
                    'closing_journal_entry_id' => $je3->id,
                    'closing_type' => PeriodClosingEntry::TYPE_RETAINED_EARNINGS,
                    'posted_by' => $closedBy,
                ]);
            }

            $period->update([
                'status' => FinancialPeriod::STATUS_CLOSED,
                'closed_by' => $closedBy,
                'closed_at' => now(),
            ]);

            return $period->refresh();
        });
    }

    public function openPeriods(string $businessId): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialPeriod::query()
            ->where('business_id', $businessId)
            ->where('status', FinancialPeriod::STATUS_OPEN)
            ->orderBy('period_start')
            ->get();
    }

    public function currentPeriod(string $businessId): ?FinancialPeriod
    {
        return $this->findByDate($businessId, now()->toDateString());
    }

    public function periodsForBusiness(string $businessId): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialPeriod::query()
            ->where('business_id', $businessId)
            ->orderBy('period_start')
            ->get();
    }
}
