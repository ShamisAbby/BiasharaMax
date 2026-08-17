<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\BankTransaction;
use App\Domain\Finance\Services\BankAccountService;
use App\Domain\Finance\Services\BankReconciliationService;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Exceptions\BankReconciliationException;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function seedCoaAndGetBankAccount(string $businessId): array
    {
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $bankGlAccount = Account::query()
            ->where('business_id', $businessId)
            ->where('code', '1010')
            ->firstOrFail();

        $bankAccount = app(BankAccountService::class)->create($businessId, [
            'account_id' => $bankGlAccount->id,
            'bank_name' => 'Test Bank',
            'account_number' => '123456',
            'account_holder_name' => 'Acme Corp',
            'opening_balance' => '10000.00',
            'opening_date' => '2026-01-01',
        ]);

        return [$bankGlAccount, $bankAccount];
    }

    public function test_creating_a_bank_account_with_opening_balance_creates_debit_transaction(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        [, $bankAccount] = $this->seedCoaAndGetBankAccount($business->id);

        $this->assertEquals('10000.00', $bankAccount->currentBalance());

        $transaction = BankTransaction::query()
            ->where('bank_account_id', $bankAccount->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(BankTransaction::TYPE_DEBIT, $transaction->type);
        $this->assertEquals('10000.00', $transaction->amount);
    }

    public function test_transfer_between_bank_accounts_posts_journal_entry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $cashGlAccount = Account::query()->where('business_id', $business->id)->where('code', '1000')->firstOrFail();
        $bankGlAccount = Account::query()->where('business_id', $business->id)->where('code', '1010')->firstOrFail();

        $service = app(BankAccountService::class);

        $cashAccount = $service->create($business->id, [
            'account_id' => $cashGlAccount->id,
            'bank_name' => 'Petty Cash',
            'account_number' => 'CASH-01',
            'account_holder_name' => 'Acme Corp',
            'opening_balance' => '5000.00',
            'opening_date' => '2026-01-01',
        ]);

        $bankAccount = $service->create($business->id, [
            'account_id' => $bankGlAccount->id,
            'bank_name' => 'Main Bank',
            'account_number' => 'BANK-01',
            'account_holder_name' => 'Acme Corp',
            'opening_balance' => '20000.00',
            'opening_date' => '2026-01-01',
        ]);

        $journalEntry = $service->transfer(
            $bankAccount,
            $cashAccount,
            '1000.00',
            '2026-06-01',
            $owner->id,
            'REF-001',
            'Transfer to petty cash',
        );

        $this->assertNotNull($journalEntry);
        $this->assertEquals('posted', $journalEntry->status);

        // Bank (20000) loses 1000 → 19000; Cash (5000) gains 1000 → 6000
        $this->assertEquals('6000.00', $cashAccount->fresh()->currentBalance());
        $this->assertEquals('19000.00', $bankAccount->fresh()->currentBalance());

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bankAccount->id,
            'type' => BankTransaction::TYPE_CREDIT,
            'amount' => '1000.00',
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $cashAccount->id,
            'type' => BankTransaction::TYPE_DEBIT,
            'amount' => '1000.00',
        ]);
    }

    public function test_reconciliation_complete_fails_when_difference_is_nonzero(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        [, $bankAccount] = $this->seedCoaAndGetBankAccount($business->id);

        $reconService = app(BankReconciliationService::class);

        $reconciliation = $reconService->startReconciliation(
            $bankAccount,
            '2026-01-01',
            '2026-01-31',
            '99999.00',
            $owner->id,
        );

        $this->expectException(BankReconciliationException::class);
        $reconService->completeReconciliation($reconciliation);
    }

    public function test_reconciliation_can_be_completed_when_balanced(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        [, $bankAccount] = $this->seedCoaAndGetBankAccount($business->id);

        $reconService = app(BankReconciliationService::class);

        $bookBalance = $bankAccount->currentBalance();

        $reconciliation = $reconService->startReconciliation(
            $bankAccount,
            '2026-01-01',
            '2026-01-31',
            $bookBalance,
            $owner->id,
        );

        $this->assertEquals('0.00', $reconciliation->difference);

        $completed = $reconService->completeReconciliation($reconciliation);

        $this->assertEquals('completed', $completed->status);
    }

    public function test_mark_reconciled_toggles_transaction_status(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        [, $bankAccount] = $this->seedCoaAndGetBankAccount($business->id);

        $transaction = BankTransaction::query()
            ->where('bank_account_id', $bankAccount->id)
            ->firstOrFail();

        $this->assertEquals(BankTransaction::STATUS_RECONCILED, $transaction->reconciliation_status);

        $reconService = app(BankReconciliationService::class);
        $bookBalance = $bankAccount->currentBalance();

        $reconciliation = $reconService->startReconciliation(
            $bankAccount, '2026-01-01', '2026-01-31', $bookBalance, $owner->id
        );

        $reconService->markReconciled($transaction, $reconciliation);
        $this->assertEquals(BankTransaction::STATUS_UNRECONCILED, $transaction->fresh()->reconciliation_status);

        $reconService->markReconciled($transaction, $reconciliation);
        $this->assertEquals(BankTransaction::STATUS_RECONCILED, $transaction->fresh()->reconciliation_status);
    }
}
