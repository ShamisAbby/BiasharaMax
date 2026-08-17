<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\BankTransaction;
use App\Domain\Finance\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class BankAccountService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly ChartOfAccountsService $accounts,
    ) {}

    public function create(string $businessId, array $data): BankAccount
    {
        return DB::transaction(function () use ($businessId, $data) {
            $bankAccount = BankAccount::create([
                'business_id' => $businessId,
                'account_id' => $data['account_id'],
                'bank_name' => $data['bank_name'],
                'account_number' => $data['account_number'],
                'account_holder_name' => $data['account_holder_name'],
                'currency_id' => $data['currency_id'] ?? null,
                'opening_balance' => $data['opening_balance'] ?? '0.00',
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $openingBalance = bcadd((string) ($data['opening_balance'] ?? 0), '0', 2);
            if (bccomp($openingBalance, '0', 2) > 0) {
                BankTransaction::create([
                    'business_id' => $businessId,
                    'bank_account_id' => $bankAccount->id,
                    'transaction_date' => $data['opening_date'] ?? now()->toDateString(),
                    'type' => BankTransaction::TYPE_DEBIT,
                    'amount' => $openingBalance,
                    'description' => 'Opening balance',
                    'reconciliation_status' => BankTransaction::STATUS_RECONCILED,
                    'reconciled_at' => now(),
                    'created_by' => $data['created_by'] ?? null,
                ]);
            }

            return $bankAccount;
        });
    }

    public function update(BankAccount $bankAccount, array $data): BankAccount
    {
        $allowed = ['bank_name', 'account_number', 'account_holder_name', 'currency_id', 'is_active', 'updated_by'];
        $bankAccount->update(array_intersect_key($data, array_flip($allowed)));

        return $bankAccount->refresh();
    }

    public function delete(BankAccount $bankAccount): void
    {
        $bankAccount->delete();
    }

    public function transfer(
        BankAccount $from,
        BankAccount $to,
        string $amount,
        string $date,
        string $createdBy,
        ?string $reference = null,
        ?string $description = null
    ): JournalEntry {
        return DB::transaction(function () use ($from, $to, $amount, $date, $createdBy, $reference, $description) {
            $fromAccount = $from->account;
            $toAccount = $to->account;
            $businessId = $from->business_id;
            $desc = $description ?? "Transfer from {$from->bank_name} to {$to->bank_name}";

            $journalEntry = $this->posting->postImmediately($businessId, [
                'entry_date' => $date,
                'type' => JournalEntry::TYPE_MANUAL,
                'description' => $desc,
            ], [
                ['account_id' => $toAccount->id, 'debit' => $amount, 'credit' => '0', 'description' => $desc],
                ['account_id' => $fromAccount->id, 'debit' => '0', 'credit' => $amount, 'description' => $desc],
            ], $createdBy);

            BankTransaction::create([
                'business_id' => $businessId,
                'bank_account_id' => $from->id,
                'journal_entry_id' => $journalEntry->id,
                'transaction_date' => $date,
                'type' => BankTransaction::TYPE_CREDIT,
                'amount' => $amount,
                'reference' => $reference,
                'description' => $desc,
                'created_by' => $createdBy,
            ]);

            BankTransaction::create([
                'business_id' => $businessId,
                'bank_account_id' => $to->id,
                'journal_entry_id' => $journalEntry->id,
                'transaction_date' => $date,
                'type' => BankTransaction::TYPE_DEBIT,
                'amount' => $amount,
                'reference' => $reference,
                'description' => $desc,
                'created_by' => $createdBy,
            ]);

            return $journalEntry;
        });
    }

    /**
     * Called by AutoPostingService when a cash/bank payment is posted.
     * Silently no-ops if no BankAccount maps to the given GL account.
     */
    public function recordTransactionForAccount(
        string $businessId,
        string $accountId,
        string $type,
        string $amount,
        string $date,
        ?string $journalEntryId,
        ?string $description,
        ?string $reference,
        ?string $createdBy
    ): void {
        $bankAccount = BankAccount::query()
            ->where('business_id', $businessId)
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->first();

        if (! $bankAccount) {
            return;
        }

        BankTransaction::create([
            'business_id' => $businessId,
            'bank_account_id' => $bankAccount->id,
            'journal_entry_id' => $journalEntryId,
            'transaction_date' => $date,
            'type' => $type,
            'amount' => $amount,
            'reference' => $reference,
            'description' => $description,
            'created_by' => $createdBy,
        ]);
    }

    public function listForBusiness(string $businessId): \Illuminate\Database\Eloquent\Collection
    {
        return BankAccount::query()
            ->where('business_id', $businessId)
            ->with(['account', 'currency'])
            ->orderBy('bank_name')
            ->get();
    }

    public function findInBusiness(string $bankAccountId, string $businessId): BankAccount
    {
        return BankAccount::query()
            ->where('id', $bankAccountId)
            ->where('business_id', $businessId)
            ->firstOrFail();
    }
}
