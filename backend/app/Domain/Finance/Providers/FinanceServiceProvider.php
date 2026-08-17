<?php

namespace App\Domain\Finance\Providers;

use App\Domain\Accounting\Events\ExpenseMarkedPaid;
use App\Domain\Accounting\Events\IncomeRecorded;
use App\Domain\Finance\Listeners\PostJournalEntryForExpensePaid;
use App\Domain\Finance\Listeners\PostJournalEntryForGoodsReceived;
use App\Domain\Finance\Listeners\PostJournalEntryForIncomeRecorded;
use App\Domain\Finance\Listeners\PostJournalEntryForSaleCompleted;
use App\Domain\Finance\Listeners\PostJournalEntryForSalePayment;
use App\Domain\Finance\Listeners\PostJournalEntryForSaleReturnApproved;
use App\Domain\Finance\Listeners\PostJournalEntryForSaleVoided;
use App\Domain\Finance\Listeners\PostJournalEntryForSupplierPayment;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Models\FixedAsset;
use App\Domain\Finance\Models\TaxConfiguration;
use App\Domain\Finance\Policies\AccountPolicy;
use App\Domain\Finance\Policies\BankAccountPolicy;
use App\Domain\Finance\Policies\BudgetPolicy;
use App\Domain\Finance\Policies\FinancialPeriodPolicy;
use App\Domain\Finance\Policies\FixedAssetPolicy;
use App\Domain\Finance\Policies\JournalEntryPolicy;
use App\Domain\Finance\Policies\TaxConfigurationPolicy;
use App\Domain\Purchasing\Events\GoodsReceived;
use App\Domain\Purchasing\Events\SupplierPaymentRecorded;
use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Sales\Events\SalePaymentRecorded;
use App\Domain\Sales\Events\SaleReturnApproved;
use App\Domain\Sales\Events\SaleVoided;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(TaxConfiguration::class, TaxConfigurationPolicy::class);
        Gate::policy(FixedAsset::class, FixedAssetPolicy::class);
        Gate::policy(FinancialPeriod::class, FinancialPeriodPolicy::class);

        Event::listen(SaleCompleted::class, PostJournalEntryForSaleCompleted::class);
        Event::listen(SalePaymentRecorded::class, PostJournalEntryForSalePayment::class);
        Event::listen(SaleVoided::class, PostJournalEntryForSaleVoided::class);
        Event::listen(SaleReturnApproved::class, PostJournalEntryForSaleReturnApproved::class);
        Event::listen(ExpenseMarkedPaid::class, PostJournalEntryForExpensePaid::class);
        Event::listen(IncomeRecorded::class, PostJournalEntryForIncomeRecorded::class);
        Event::listen(GoodsReceived::class, PostJournalEntryForGoodsReceived::class);
        Event::listen(SupplierPaymentRecorded::class, PostJournalEntryForSupplierPayment::class);
    }
}
