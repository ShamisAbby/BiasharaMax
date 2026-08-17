<?php

namespace Tests\Feature\Finance;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\Income;
use App\Domain\Accounting\Services\ExpenseService;
use App\Domain\Accounting\Services\IncomeService;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\BankTransaction;
use App\Domain\Finance\Models\FixedAsset;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\TaxConfiguration;
use App\Domain\Finance\Services\BankAccountService;
use App\Domain\Finance\Services\BankReconciliationService;
use App\Domain\Finance\Services\BudgetService;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\FixedAssetService;
use App\Domain\Finance\Services\JournalPostingService;
use App\Domain\Finance\Services\PaymentGatewayService;
use App\Domain\Finance\Services\PaymentTransactionService;
use App\Domain\Finance\Services\TaxService;
use App\Domain\Localization\Models\TaxRate;
use App\Domain\Subscription\Models\SubscriptionTransaction;
use App\Domain\Subscription\Services\SubscriptionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Proves docs/ADR/0002-money-format-migration.md's dual-write invariant for
 * the Finance context (sixth and last of six): journal_lines, bank_accounts,
 * bank_transactions, bank_reconciliations, budget_lines, tax_transactions,
 * fixed_assets, depreciation_schedules, payment_transactions,
 * payment_gateways, expenses, incomes, subscription_plans, subscriptions,
 * subscription_transactions.
 *
 * Every model here got SyncsMoneyMinorColumns and nothing else — no service
 * arithmetic was touched, since every service already produces correct
 * decimal(x,2) values via bcmath and the trait derives `_minor` losslessly
 * from that. The one Finance-specific risk this suite exists to close is
 * the ledger's core invariant: JournalPostingService::assertBalanced()
 * enforces SUM(debit) == SUM(credit) in decimal *before* any line is
 * persisted, and round(decimal*100) is an exact, order-preserving
 * transformation applied identically to both sides — so
 * SUM(debit_minor) == SUM(credit_minor) is guaranteed to hold once
 * `_minor` is derived, not just something to hope is true. The first test
 * below proves that in practice through the real posting service, across
 * a multi-line (not just 1-debit/1-credit) entry.
 */
class MoneyMinorSyncTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function assertDecimalMinorAgree(string $decimal, ?int $minor, string $label): void
    {
        $this->assertNotNull($minor, "{$label} _minor was not derived (null)");
        $this->assertSame(
            $decimal,
            bcdiv((string) $minor, '100', 2),
            "{$label} decimal/_minor mismatch: {$decimal} vs {$minor}"
        );
    }

    public function test_journal_entry_posting_derives_minor_and_debit_credit_minor_sums_balance(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $cash = Account::query()->where('business_id', $business->id)->where('code', '1000')->firstOrFail();
        $revenue = Account::query()->where('business_id', $business->id)->where('code', '4000')->firstOrFail();
        $tax = Account::query()->where('business_id', $business->id)->where('code', '2100')->firstOrFail();

        // Three lines, not a simple 1:1 debit/credit pair — one debit split
        // across two credits — so the invariant proof isn't trivially true
        // just because there happen to be exactly two rows.
        $entry = app(JournalPostingService::class)->postImmediately($business->id, [
            'entry_date' => now()->toDateString(),
            'description' => 'Cash sale with tax',
        ], [
            ['account_id' => $cash->id, 'debit' => '1160.00', 'credit' => '0'],
            ['account_id' => $revenue->id, 'debit' => '0', 'credit' => '1000.00'],
            ['account_id' => $tax->id, 'debit' => '0', 'credit' => '160.00'],
        ], $owner->id);

        $totalDebitMinor = 0;
        $totalCreditMinor = 0;

        foreach ($entry->lines as $line) {
            $this->assertDecimalMinorAgree((string) $line->debit, $line->debit_minor, 'journal_line.debit');
            $this->assertDecimalMinorAgree((string) $line->credit, $line->credit_minor, 'journal_line.credit');
            $totalDebitMinor += $line->debit_minor;
            $totalCreditMinor += $line->credit_minor;
        }

        $this->assertSame(116000, $totalDebitMinor);
        $this->assertSame(116000, $totalCreditMinor);
        $this->assertSame($totalDebitMinor, $totalCreditMinor, 'SUM(debit_minor) must equal SUM(credit_minor) per journal entry');
    }

    public function test_journal_line_foreign_amount_derives_minor_when_set(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $cash = Account::query()->where('business_id', $business->id)->where('code', '1000')->firstOrFail();
        $revenue = Account::query()->where('business_id', $business->id)->where('code', '4000')->firstOrFail();

        $entry = app(JournalPostingService::class)->postImmediately($business->id, [
            'entry_date' => now()->toDateString(),
        ], [
            ['account_id' => $cash->id, 'debit' => '500.00', 'credit' => '0', 'foreign_amount' => '200.00'],
            ['account_id' => $revenue->id, 'debit' => '0', 'credit' => '500.00'],
        ], $owner->id);

        $debitLine = $entry->lines->firstWhere('account_id', $cash->id);
        $this->assertDecimalMinorAgree((string) $debitLine->foreign_amount, $debitLine->foreign_amount_minor, 'journal_line.foreign_amount');
    }

    public function test_bank_account_opening_balance_and_transaction_derive_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $bankGlAccount = Account::query()->where('business_id', $business->id)->where('code', '1010')->firstOrFail();

        $bankAccount = app(BankAccountService::class)->create($business->id, [
            'account_id' => $bankGlAccount->id,
            'bank_name' => 'Test Bank',
            'account_number' => '123456',
            'account_holder_name' => 'Acme Corp',
            'opening_balance' => '10000.00',
            'opening_date' => '2026-01-01',
        ]);

        $this->assertDecimalMinorAgree((string) $bankAccount->opening_balance, $bankAccount->opening_balance_minor, 'bank_account.opening_balance');

        $transaction = BankTransaction::query()->where('bank_account_id', $bankAccount->id)->firstOrFail();
        $this->assertDecimalMinorAgree((string) $transaction->amount, $transaction->amount_minor, 'bank_transaction.amount');
    }

    public function test_bank_reconciliation_derives_minor_on_all_three_balances(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $bankGlAccount = Account::query()->where('business_id', $business->id)->where('code', '1010')->firstOrFail();
        $bankAccount = app(BankAccountService::class)->create($business->id, [
            'account_id' => $bankGlAccount->id,
            'bank_name' => 'Test Bank',
            'account_number' => '123456',
            'account_holder_name' => 'Acme Corp',
            'opening_balance' => '10000.00',
            'opening_date' => '2026-01-01',
        ]);

        $reconciliation = app(BankReconciliationService::class)->startReconciliation(
            $bankAccount, '2026-01-01', '2026-01-31', '9500.00', $owner->id,
        );

        foreach (['statement_balance', 'book_balance', 'difference'] as $field) {
            $this->assertDecimalMinorAgree((string) $reconciliation->{$field}, $reconciliation->{"{$field}_minor"}, "bank_reconciliation.{$field}");
        }
        // difference is negative here (9500 statement vs 10000 book) — proves
        // the trait handles a negative decimal->minor derivation correctly.
        $this->assertSame(-50000, $reconciliation->difference_minor);
    }

    public function test_budget_line_derives_budgeted_amount_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $expenseAccount = Account::query()->where('business_id', $business->id)->where('code', '5900')->firstOrFail();

        $budget = app(BudgetService::class)->create($business->id, [
            'fiscal_year' => 2026,
            'name' => 'FY2026 Operating Budget',
        ], [
            ['account_id' => $expenseAccount->id, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'budgeted_amount' => '2500.00'],
        ]);

        $line = $budget->lines()->firstOrFail();
        $this->assertDecimalMinorAgree((string) $line->budgeted_amount, $line->budgeted_amount_minor, 'budget_line.budgeted_amount');
    }

    public function test_tax_transaction_derives_taxable_and_tax_amount_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $vatAccount = Account::query()->where('business_id', $business->id)->where('code', '2120')->firstOrFail();
        $taxRate = TaxRate::factory()->create(['rate' => '18.00']);

        $config = TaxConfiguration::create([
            'business_id' => $business->id,
            'tax_rate_id' => $taxRate->id,
            'tax_type' => TaxConfiguration::TYPE_VAT,
            'applies_to' => TaxConfiguration::APPLIES_SALES,
            'account_id' => $vatAccount->id,
            'is_active' => true,
        ]);

        $cash = Account::query()->where('business_id', $business->id)->where('code', '1000')->firstOrFail();
        $revenue = Account::query()->where('business_id', $business->id)->where('code', '4000')->firstOrFail();
        $entry = app(JournalPostingService::class)->postImmediately($business->id, [
            'entry_date' => now()->toDateString(),
        ], [
            ['account_id' => $cash->id, 'debit' => '1180.00', 'credit' => '0'],
            ['account_id' => $revenue->id, 'debit' => '0', 'credit' => '1000.00'],
            ['account_id' => $vatAccount->id, 'debit' => '0', 'credit' => '180.00'],
        ], $owner->id);

        $taxTransaction = app(TaxService::class)->recordTaxTransaction(
            $business->id, $config, $entry, TaxConfiguration::TYPE_VAT === $config->tax_type ? 'output' : 'input',
            '1000.00', '180.00', now()->toDateString(), '2026-01-01', '2026-01-31', $owner->id,
        );

        $this->assertDecimalMinorAgree((string) $taxTransaction->taxable_amount, $taxTransaction->taxable_amount_minor, 'tax_transaction.taxable_amount');
        $this->assertDecimalMinorAgree((string) $taxTransaction->tax_amount, $taxTransaction->tax_amount_minor, 'tax_transaction.tax_amount');
    }

    public function test_fixed_asset_and_depreciation_schedule_derive_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $assetAccount = Account::query()->where('business_id', $business->id)->where('code', '1010')->firstOrFail();

        $asset = app(FixedAssetService::class)->create($business->id, [
            'asset_code' => 'VEH-001',
            'asset_name' => 'Company Vehicle',
            'category' => FixedAsset::CATEGORY_VEHICLE,
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => '120000.00',
            'account_id' => $assetAccount->id,
            'useful_life_months' => 60,
            'residual_value' => '20000.00',
            'depreciation_method' => FixedAsset::METHOD_STRAIGHT_LINE,
            'created_by' => $owner->id,
        ]);

        $this->assertDecimalMinorAgree((string) $asset->acquisition_cost, $asset->acquisition_cost_minor, 'fixed_asset.acquisition_cost');
        $this->assertDecimalMinorAgree((string) $asset->residual_value, $asset->residual_value_minor, 'fixed_asset.residual_value');

        app(FixedAssetService::class)->postMonthlyDepreciation($business->id, '2026-02', $owner->id);
        $schedule = $asset->depreciationSchedule()->where('period_date', '2026-02-01')->firstOrFail();

        foreach (['depreciation_amount', 'accumulated_depreciation', 'book_value'] as $field) {
            $this->assertDecimalMinorAgree((string) $schedule->{$field}, $schedule->{"{$field}_minor"}, "depreciation_schedule.{$field}");
        }

        $cashAccount = Account::query()->where('business_id', $business->id)->where('code', '1000')->firstOrFail();
        app(FixedAssetService::class)->dispose($asset, '2026-06-30', '110000.00', $cashAccount->id, $owner->id);

        $asset->refresh();
        $this->assertDecimalMinorAgree((string) $asset->disposal_proceeds, $asset->disposal_proceeds_minor, 'fixed_asset.disposal_proceeds (nullable field, set on disposal)');
    }

    public function test_payment_gateway_and_payment_transaction_derive_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $gateway = app(PaymentGatewayService::class)->create([
            'name' => 'Manual Bank Transfer',
            'slug' => 'manual-bank-'.uniqid(),
            'provider' => PaymentGateway::PROVIDER_BANK_TRANSFER,
            'fee_fixed' => '5.00',
        ]);
        $this->assertDecimalMinorAgree((string) $gateway->fee_fixed, $gateway->fee_fixed_minor, 'payment_gateway.fee_fixed');

        $transaction = app(PaymentTransactionService::class)->recordManual([
            'business_id' => $business->id,
            'payment_gateway_id' => $gateway->id,
            'amount' => '1000.00',
            'currency' => 'TZS',
            'tax_amount' => '160.00',
            'discount_amount' => '50.00',
            'fee_amount' => '5.00',
            'commission_amount' => '10.00',
        ]);

        foreach (['amount', 'tax_amount', 'discount_amount', 'fee_amount', 'commission_amount'] as $field) {
            $this->assertDecimalMinorAgree((string) $transaction->{$field}, $transaction->{"{$field}_minor"}, "payment_transaction.{$field}");
        }
    }

    public function test_expense_and_income_derive_amount_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $expense = app(ExpenseService::class)->create([
            'business_id' => $business->id, 'title' => 'Fuel', 'amount' => '80.00',
            'expense_date' => now()->toDateString(),
        ]);
        $this->assertDecimalMinorAgree((string) $expense->amount, $expense->amount_minor, 'expense.amount');

        $income = app(IncomeService::class)->create([
            'business_id' => $business->id, 'title' => 'Consulting', 'category' => Income::CATEGORY_SERVICE,
            'amount' => '300.00', 'income_date' => now()->toDateString(), 'payment_method' => 'cash',
        ]);
        $this->assertDecimalMinorAgree((string) $income->amount, $income->amount_minor, 'income.amount');
    }

    public function test_subscription_plan_price_fields_derive_minor_on_registration(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        // createOwnerWithBusiness() creates its SubscriptionPlan via the
        // factory, which only ever sets the legacy decimal price_* fields —
        // *_minor is expected to come from SyncsMoneyMinorColumns.
        $plan = $business->subscription->plan;

        foreach (['price_monthly', 'price_quarterly', 'price_yearly', 'price_lifetime'] as $field) {
            $this->assertDecimalMinorAgree((string) $plan->{$field}, $plan->{"{$field}_minor"}, "subscription_plan.{$field}");
        }
    }

    public function test_subscription_custom_price_and_transaction_amount_derive_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $subscription = $business->subscription;
        $plan = $subscription->plan;

        app(SubscriptionService::class)->changePlan($subscription, $plan, 'monthly', customPrice: 4500.00);
        $subscription->refresh();
        $this->assertDecimalMinorAgree((string) $subscription->custom_price, $subscription->custom_price_minor, 'subscription.custom_price');

        app(SubscriptionService::class)->renewWithPayment($subscription, ['amount' => '4500.00']);
        $transaction = SubscriptionTransaction::query()->where('subscription_id', $subscription->id)->firstOrFail();
        $this->assertDecimalMinorAgree((string) $transaction->amount, $transaction->amount_minor, 'subscription_transaction.amount');
    }
}
