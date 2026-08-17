<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Exceptions\BankReconciliationException;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\BankReconciliation;
use App\Domain\Finance\Models\BankTransaction;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    public function startReconciliation(
        BankAccount $bankAccount,
        string $periodStart,
        string $periodEnd,
        string $statementBalance,
        string $reconciledBy
    ): BankReconciliation {
        $bookBalance = $this->computeBookBalance($bankAccount, $periodStart, $periodEnd);
        $difference = bcsub($statementBalance, $bookBalance, 2);

        return BankReconciliation::create([
            'business_id' => $bankAccount->business_id,
            'bank_account_id' => $bankAccount->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'statement_balance' => $statementBalance,
            'book_balance' => $bookBalance,
            'difference' => $difference,
            'status' => BankReconciliation::STATUS_DRAFT,
            'reconciled_by' => $reconciledBy,
        ]);
    }

    public function markReconciled(BankTransaction $transaction, BankReconciliation $reconciliation): void
    {
        DB::transaction(function () use ($transaction, $reconciliation) {
            if ($transaction->isReconciled()) {
                $transaction->update([
                    'reconciliation_status' => BankTransaction::STATUS_UNRECONCILED,
                    'reconciled_at' => null,
                ]);
            } else {
                $transaction->update([
                    'reconciliation_status' => BankTransaction::STATUS_RECONCILED,
                    'reconciled_at' => now(),
                ]);
            }

            $this->recomputeDifference($reconciliation);
        });
    }

    public function completeReconciliation(BankReconciliation $reconciliation): BankReconciliation
    {
        if (! $reconciliation->isBalanced()) {
            throw BankReconciliationException::notBalanced($reconciliation->difference);
        }

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $reconciliation->refresh();
    }

    public function getUnreconciledTransactions(BankAccount $bankAccount, string $periodStart, string $periodEnd): \Illuminate\Database\Eloquent\Collection
    {
        return BankTransaction::query()
            ->where('bank_account_id', $bankAccount->id)
            ->where('reconciliation_status', BankTransaction::STATUS_UNRECONCILED)
            ->whereBetween('transaction_date', [$periodStart, $periodEnd])
            ->orderBy('transaction_date')
            ->get();
    }

    private function computeBookBalance(BankAccount $bankAccount, string $periodStart, string $periodEnd): string
    {
        $debits = BankTransaction::query()
            ->where('bank_account_id', $bankAccount->id)
            ->where('type', BankTransaction::TYPE_DEBIT)
            ->where('transaction_date', '<=', $periodEnd)
            ->sum('amount');

        $credits = BankTransaction::query()
            ->where('bank_account_id', $bankAccount->id)
            ->where('type', BankTransaction::TYPE_CREDIT)
            ->where('transaction_date', '<=', $periodEnd)
            ->sum('amount');

        return bcsub((string) $debits, (string) $credits, 2);
    }

    private function recomputeDifference(BankReconciliation $reconciliation): void
    {
        $bankAccount = $reconciliation->bankAccount;
        $bookBalance = $this->computeBookBalance($bankAccount, $reconciliation->period_start->toDateString(), $reconciliation->period_end->toDateString());
        $difference = bcsub((string) $reconciliation->statement_balance, $bookBalance, 2);

        $reconciliation->update([
            'book_balance' => $bookBalance,
            'difference' => $difference,
        ]);
    }
}
