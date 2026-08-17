<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\FinancialStatementService;
use App\Domain\Finance\Services\GeneralLedgerService;
use App\Domain\Finance\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class GeneralLedgerAndStatementsTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private ChartOfAccountsService $coa;

    private JournalPostingService $posting;

    private GeneralLedgerService $ledger;

    private FinancialStatementService $statements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coa = app(ChartOfAccountsService::class);
        $this->posting = app(JournalPostingService::class);
        $this->ledger = app(GeneralLedgerService::class);
        $this->statements = app(FinancialStatementService::class);
    }

    public function test_account_balance_reflects_posted_lines_signed_by_normal_side(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $cash = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $this->posting->postImmediately($business->id, ['entry_date' => '2026-01-10'], [
            ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500],
        ]);

        $this->assertSame('500.00', $this->ledger->accountBalance($cash->fresh()));
        $this->assertSame('500.00', $this->ledger->accountBalance($revenue->fresh()));
    }

    public function test_trial_balance_always_totals_to_zero_difference(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $cash = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);
        $expenseAccount = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_GENERAL_EXPENSE);
        $bank = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_BANK);

        $this->posting->postImmediately($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->posting->postImmediately($business->id, [], [
            ['account_id' => $expenseAccount->id, 'debit' => 150, 'credit' => 0],
            ['account_id' => $bank->id, 'debit' => 0, 'credit' => 150],
        ]);

        $trialBalance = $this->ledger->trialBalance($business->id);

        $this->assertSame($trialBalance['total_debit'], $trialBalance['total_credit']);
        $this->assertSame('1150.00', $trialBalance['total_debit']);
    }

    public function test_profit_and_loss_matches_a_known_fixture(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $cash = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);
        $cogs = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_COST_OF_GOODS_SOLD);
        $inventory = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_INVENTORY);

        $this->posting->postImmediately($business->id, ['entry_date' => '2026-02-01'], [
            ['account_id' => $cash->id, 'debit' => 2000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 2000],
        ]);

        $this->posting->postImmediately($business->id, ['entry_date' => '2026-02-01'], [
            ['account_id' => $cogs->id, 'debit' => 1200, 'credit' => 0],
            ['account_id' => $inventory->id, 'debit' => 0, 'credit' => 1200],
        ]);

        $pAndL = $this->statements->profitAndLoss($business->id, '2026-02-01', '2026-02-28');

        $this->assertSame('2000.00', $pAndL['total_revenue']);
        $this->assertSame('1200.00', $pAndL['total_expenses']);
        $this->assertSame('800.00', $pAndL['net_profit']);
    }

    public function test_balance_sheet_equation_holds_after_postings(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $cash = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);
        $accountsPayable = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);
        $inventory = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_INVENTORY);

        $this->posting->postImmediately($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 3000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 3000],
        ]);

        $this->posting->postImmediately($business->id, [], [
            ['account_id' => $inventory->id, 'debit' => 800, 'credit' => 0],
            ['account_id' => $accountsPayable->id, 'debit' => 0, 'credit' => 800],
        ]);

        $balanceSheet = $this->statements->balanceSheet($business->id, Carbon::today()->toDateString());

        $this->assertTrue($balanceSheet['is_balanced']);
        $this->assertSame('3800.00', $balanceSheet['total_assets']);
        $this->assertSame('800.00', $balanceSheet['total_liabilities']);
        $this->assertSame('3000.00', $balanceSheet['total_equity']);
    }

    public function test_reversing_an_entry_nets_its_account_balances_to_zero(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $cash = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $bank = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_BANK);

        $entry = $this->posting->postImmediately($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $bank->id, 'debit' => 0, 'credit' => 100],
        ]);

        $this->posting->reverse($entry);

        $this->assertSame('0.00', $this->ledger->accountBalance($cash->fresh()));
        $this->assertSame('0.00', $this->ledger->accountBalance($bank->fresh()));

        $trialBalance = $this->ledger->trialBalance($business->id);
        $this->assertSame('0.00', $trialBalance['total_debit']);
        $this->assertSame('0.00', $trialBalance['total_credit']);
        $this->assertCount(0, $trialBalance['lines']);
    }

    public function test_account_ledger_computes_a_correct_running_balance(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $cash = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $this->coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $this->posting->postImmediately($business->id, ['entry_date' => '2026-03-01'], [
            ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100],
        ]);

        $this->posting->postImmediately($business->id, ['entry_date' => '2026-03-02'], [
            ['account_id' => $cash->id, 'debit' => 50, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 50],
        ]);

        $ledgerPage = $this->ledger->accountLedger($cash->fresh());

        $this->assertSame('100.00', $ledgerPage->items()[0]->running_balance);
        $this->assertSame('150.00', $ledgerPage->items()[1]->running_balance);
    }
}
