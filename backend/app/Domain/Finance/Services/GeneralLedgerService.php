<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Models\JournalLine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GeneralLedgerService
{
    /**
     * Cumulative balance of an account from inception through $asOfDate
     * (defaults to today), signed so a normal-side balance is positive.
     */
    public function accountBalance(Account $account, ?string $asOfDate = null): string
    {
        return $this->accountActivity($account, null, $asOfDate);
    }

    /**
     * Net movement of an account between two dates (inclusive), signed so a
     * normal-side movement is positive. Used standalone for "this account's
     * balance as of X" (pass only $toDate) and for period activity in
     * FinancialStatementService (pass both bounds).
     */
    public function accountActivity(Account $account, ?string $fromDate = null, ?string $toDate = null): string
    {
        $totals = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($query) use ($fromDate, $toDate) {
                // A reversed entry's lines are real, immutable ledger history —
                // they must keep counting so the original and its mirrored
                // reversal net to zero. Only the still-posted status is what
                // changes (to "reversed"); excluding it here would leave the
                // reversal's opposite-signed lines standing alone as a fake
                // balance instead of cancelling out.
                $query->whereIn('status', [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_REVERSED]);

                if ($fromDate) {
                    $query->where('entry_date', '>=', $fromDate);
                }

                if ($toDate) {
                    $query->where('entry_date', '<=', $toDate);
                }
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (string) $totals->total_debit;
        $credit = (string) $totals->total_credit;

        return $account->isDebitNormal() ? bcsub($debit, $credit, 2) : bcsub($credit, $debit, 2);
    }

    /**
     * Every account's balance for a business, in one query.
     *
     * `accountBalance()` is fine for a single account, but the two screens
     * that show the whole chart — the General Ledger index and the Trial
     * Balance — were calling it inside a `map()` over every active account.
     * That is one aggregate query per account: a 300-account chart meant
     * 300 round trips to render one page, and it got slower every time the
     * business added an account.
     *
     * Returns raw debit/credit totals keyed by account id rather than a
     * signed balance, so callers apply `isDebitNormal()` themselves and the
     * arithmetic stays identical to the single-account path.
     *
     * @return array<string, array{debit: string, credit: string}>
     */
    public function accountBalances(string $businessId, ?string $asOfDate = null): array
    {
        $rows = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.business_id', $businessId)
            // Same status pair as accountActivity(): a reversed entry's
            // lines still count, so the original and its mirror net to zero.
            ->whereIn('journal_entries.status', [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_REVERSED])
            ->when($asOfDate, fn ($query) => $query->where('journal_entries.entry_date', '<=', $asOfDate))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id as account_id, COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $balances[$row->account_id] = [
                'debit' => (string) $row->total_debit,
                'credit' => (string) $row->total_credit,
            ];
        }

        return $balances;
    }

    /**
     * Signs a debit/credit pair from accountBalances() the way
     * accountBalance() would, so the two paths can't drift apart.
     */
    public function signBalance(Account $account, ?array $totals): string
    {
        $debit = $totals['debit'] ?? '0';
        $credit = $totals['credit'] ?? '0';

        return $account->isDebitNormal()
            ? bcsub($debit, $credit, 2)
            : bcsub($credit, $debit, 2);
    }

    /**
     * Paginated, running-balance ledger for a single account. Each row
     * carries the originating source record (Sale/PurchaseOrder/Expense/...)
     * via JournalEntry::source for drill-down.
     */
    public function accountLedger(Account $account, ?string $fromDate = null, ?string $toDate = null, int $perPage = 25): LengthAwarePaginator
    {
        $openingBalance = $fromDate
            ? $this->accountBalance($account, date('Y-m-d', strtotime($fromDate.' -1 day')))
            : '0.00';

        $paginated = JournalLine::query()
            ->where('journal_lines.account_id', $account->id)
            ->whereHas('journalEntry', function ($query) use ($fromDate, $toDate) {
                $query->whereIn('status', [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_REVERSED]);

                if ($fromDate) {
                    $query->where('entry_date', '>=', $fromDate);
                }

                if ($toDate) {
                    $query->where('entry_date', '<=', $toDate);
                }
            })
            ->with('journalEntry.source')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.entry_number')
            ->select('journal_lines.*')
            ->paginate($perPage);

        $runningBalance = $openingBalance;

        $paginated->getCollection()->transform(function (JournalLine $line) use ($account, &$runningBalance) {
            $delta = $account->isDebitNormal()
                ? bcsub((string) $line->debit, (string) $line->credit, 2)
                : bcsub((string) $line->credit, (string) $line->debit, 2);

            $runningBalance = bcadd($runningBalance, $delta, 2);
            $line->setAttribute('running_balance', $runningBalance);

            return $line;
        });

        return $paginated;
    }

    /**
     * @return array{lines: Collection<int, array{account: Account, debit: string, credit: string}>, total_debit: string, total_credit: string}
     */
    public function trialBalance(string $businessId, ?string $asOfDate = null): array
    {
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        // One aggregate query for the whole chart instead of one per row.
        $balances = $this->accountBalances($businessId, $asOfDate);

        $lines = Account::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($balances, &$totalDebit, &$totalCredit) {
                $balance = $this->signBalance($account, $balances[$account->id] ?? null);
                $isPositive = bccomp($balance, '0', 2) >= 0;
                $absBalance = $isPositive ? $balance : bcmul($balance, '-1', 2);

                $debit = $account->isDebitNormal() === $isPositive ? $absBalance : '0.00';
                $credit = $account->isDebitNormal() === $isPositive ? '0.00' : $absBalance;

                $totalDebit = bcadd($totalDebit, $debit, 2);
                $totalCredit = bcadd($totalCredit, $credit, 2);

                return ['account' => $account, 'debit' => $debit, 'credit' => $credit];
            })
            ->filter(fn (array $row) => bccomp($row['debit'], '0', 2) !== 0 || bccomp($row['credit'], '0', 2) !== 0)
            ->values();

        return ['lines' => $lines, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit];
    }
}
