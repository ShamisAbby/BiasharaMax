<?php

namespace App\Domain\Finance\Services;

use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Finance\Exceptions\AccountException;
use App\Domain\Finance\Models\Account;

/**
 * Owns the Chart of Accounts per business, including the fixed set of
 * "system" accounts that AutoPostingService resolves by stable key/code
 * rather than free-text name — so renaming an account in the UI never
 * breaks automatic posting.
 */
class ChartOfAccountsService
{
    public const KEY_CASH = 'cash';

    public const KEY_BANK = 'bank';

    public const KEY_ACCOUNTS_RECEIVABLE = 'accounts_receivable';

    public const KEY_INVENTORY = 'inventory';

    public const KEY_ACCOUNTS_PAYABLE = 'accounts_payable';

    public const KEY_SALES_TAX_PAYABLE = 'sales_tax_payable';

    public const KEY_OPENING_BALANCE_EQUITY = 'opening_balance_equity';

    public const KEY_SALES_REVENUE = 'sales_revenue';

    public const KEY_SALES_RETURNS = 'sales_returns';

    public const KEY_OTHER_INCOME = 'other_income';

    public const KEY_COST_OF_GOODS_SOLD = 'cost_of_goods_sold';

    public const KEY_GENERAL_EXPENSE = 'general_expense';

    // Phase 3: Financial Periods
    public const KEY_INCOME_SUMMARY = 'income_summary';

    public const KEY_RETAINED_EARNINGS = 'retained_earnings';

    // Phase 6: Fixed Assets
    public const KEY_ACCUMULATED_DEPRECIATION = 'accumulated_depreciation';

    public const KEY_DEPRECIATION_EXPENSE = 'depreciation_expense';

    public const KEY_GAIN_ON_DISPOSAL = 'gain_on_disposal';

    public const KEY_LOSS_ON_DISPOSAL = 'loss_on_disposal';

    // Phase 5: Tax
    public const KEY_VAT_INPUT = 'vat_input';

    public const KEY_VAT_OUTPUT = 'vat_output';

    // Phase 7: Payroll
    public const KEY_SALARY_EXPENSE = 'salary_expense';

    public const KEY_SOCIAL_SECURITY_PAYABLE = 'social_security_payable';

    public const KEY_INCOME_TAX_PAYABLE = 'income_tax_payable';

    // Phase 8: FX
    public const KEY_FX_GAIN_LOSS = 'fx_gain_loss';

    private const EXPENSE_CATEGORY_CODE_START = 6000;

    /** @var array<string, array{code:string,name:string,type:string,normal_balance:string}> */
    private const SYSTEM_ACCOUNTS = [
        self::KEY_CASH => ['code' => '1000', 'name' => 'Cash', 'type' => Account::TYPE_ASSET, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_BANK => ['code' => '1010', 'name' => 'Bank', 'type' => Account::TYPE_ASSET, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_ACCOUNTS_RECEIVABLE => ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => Account::TYPE_ASSET, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_INVENTORY => ['code' => '1200', 'name' => 'Inventory', 'type' => Account::TYPE_ASSET, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_ACCOUNTS_PAYABLE => ['code' => '2000', 'name' => 'Accounts Payable', 'type' => Account::TYPE_LIABILITY, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_SALES_TAX_PAYABLE => ['code' => '2100', 'name' => 'Sales Tax Payable', 'type' => Account::TYPE_LIABILITY, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_OPENING_BALANCE_EQUITY => ['code' => '3900', 'name' => 'Opening Balance Equity', 'type' => Account::TYPE_EQUITY, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_SALES_REVENUE => ['code' => '4000', 'name' => 'Sales Revenue', 'type' => Account::TYPE_INCOME, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_SALES_RETURNS => ['code' => '4100', 'name' => 'Sales Returns & Allowances', 'type' => Account::TYPE_INCOME, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_OTHER_INCOME => ['code' => '4900', 'name' => 'Other Income', 'type' => Account::TYPE_INCOME, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_COST_OF_GOODS_SOLD => ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => Account::TYPE_EXPENSE, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_DEPRECIATION_EXPENSE => ['code' => '5100', 'name' => 'Depreciation Expense', 'type' => Account::TYPE_EXPENSE, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_SALARY_EXPENSE => ['code' => '5200', 'name' => 'Salary & Wages Expense', 'type' => Account::TYPE_EXPENSE, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_GENERAL_EXPENSE => ['code' => '5900', 'name' => 'General & Administrative Expenses', 'type' => Account::TYPE_EXPENSE, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_LOSS_ON_DISPOSAL => ['code' => '5800', 'name' => 'Loss on Asset Disposal', 'type' => Account::TYPE_EXPENSE, 'normal_balance' => Account::BALANCE_DEBIT],
        // Accumulated Depreciation — contra asset
        self::KEY_ACCUMULATED_DEPRECIATION => ['code' => '1700', 'name' => 'Accumulated Depreciation', 'type' => Account::TYPE_ASSET, 'normal_balance' => Account::BALANCE_CREDIT],
        // Liabilities
        self::KEY_SOCIAL_SECURITY_PAYABLE => ['code' => '2200', 'name' => 'Social Security Payable', 'type' => Account::TYPE_LIABILITY, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_INCOME_TAX_PAYABLE => ['code' => '2300', 'name' => 'Income Tax Payable', 'type' => Account::TYPE_LIABILITY, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_VAT_INPUT => ['code' => '2110', 'name' => 'Input VAT (Recoverable)', 'type' => Account::TYPE_ASSET, 'normal_balance' => Account::BALANCE_DEBIT],
        self::KEY_VAT_OUTPUT => ['code' => '2120', 'name' => 'Output VAT Payable', 'type' => Account::TYPE_LIABILITY, 'normal_balance' => Account::BALANCE_CREDIT],
        // Equity
        self::KEY_RETAINED_EARNINGS => ['code' => '3800', 'name' => 'Retained Earnings', 'type' => Account::TYPE_EQUITY, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_INCOME_SUMMARY => ['code' => '9000', 'name' => 'Income Summary', 'type' => Account::TYPE_EQUITY, 'normal_balance' => Account::BALANCE_CREDIT],
        // Income
        self::KEY_GAIN_ON_DISPOSAL => ['code' => '4800', 'name' => 'Gain on Asset Disposal', 'type' => Account::TYPE_INCOME, 'normal_balance' => Account::BALANCE_CREDIT],
        self::KEY_FX_GAIN_LOSS => ['code' => '4950', 'name' => 'Foreign Exchange Gain/Loss', 'type' => Account::TYPE_INCOME, 'normal_balance' => Account::BALANCE_CREDIT],
    ];

    public function seedDefaults(string $businessId): void
    {
        foreach (self::SYSTEM_ACCOUNTS as $key => $definition) {
            Account::query()->firstOrCreate(
                ['business_id' => $businessId, 'code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'normal_balance' => $definition['normal_balance'],
                    'is_system_default' => true,
                    'subtype' => $key,
                ],
            );
        }

        $this->seedExpenseCategoryAccounts($businessId);
    }

    /**
     * One Expense-type account per existing ExpenseCategory row, so an
     * expense recorded under "Rent" posts to a "Rent" account rather than a
     * single undifferentiated expense bucket.
     */
    public function seedExpenseCategoryAccounts(string $businessId): void
    {
        $nextCode = max(
            self::EXPENSE_CATEGORY_CODE_START,
            1 + (int) Account::query()
                ->where('business_id', $businessId)
                ->where('code', '>=', (string) self::EXPENSE_CATEGORY_CODE_START)
                ->max('code'),
        );

        ExpenseCategory::query()->where('business_id', $businessId)->get()->each(function (ExpenseCategory $category) use ($businessId, &$nextCode) {
            $subtype = "expense_category:{$category->id}";

            if (Account::query()->where('business_id', $businessId)->where('subtype', $subtype)->exists()) {
                return;
            }

            Account::create([
                'business_id' => $businessId,
                'code' => (string) $nextCode,
                'name' => $category->name,
                'type' => Account::TYPE_EXPENSE,
                'subtype' => $subtype,
                'normal_balance' => Account::BALANCE_DEBIT,
                'is_system_default' => true,
            ]);

            $nextCode++;
        });
    }

    public function resolveSystemAccount(string $businessId, string $key): Account
    {
        $definition = self::SYSTEM_ACCOUNTS[$key] ?? null;

        if ($definition === null) {
            throw AccountException::unknownSystemAccountKey($key);
        }

        return Account::query()
            ->where('business_id', $businessId)
            ->where('code', $definition['code'])
            ->firstOrFail();
    }

    /**
     * Falls back to the general expense account if this category has no
     * mapped account yet (e.g. it was created after the last seed run).
     */
    public function resolveExpenseCategoryAccount(string $businessId, string $expenseCategoryId): Account
    {
        $account = Account::query()
            ->where('business_id', $businessId)
            ->where('subtype', "expense_category:{$expenseCategoryId}")
            ->first();

        return $account ?? $this->resolveSystemAccount($businessId, self::KEY_GENERAL_EXPENSE);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $businessId, array $data): Account
    {
        $data['business_id'] = $businessId;
        $data['is_system_default'] = false;

        return Account::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Account $account, array $data): Account
    {
        unset($data['code'], $data['type'], $data['normal_balance'], $data['is_system_default']);

        $account->update($data);

        return $account->refresh();
    }

    public function deactivate(Account $account): Account
    {
        $account->update(['is_active' => false]);

        return $account->refresh();
    }

    public function delete(Account $account): void
    {
        if ($account->is_system_default) {
            throw AccountException::systemAccountUndeletable($account->name);
        }

        if ($account->journalLines()->exists()) {
            throw AccountException::accountHasJournalActivity($account->name);
        }

        $account->delete();
    }
}
