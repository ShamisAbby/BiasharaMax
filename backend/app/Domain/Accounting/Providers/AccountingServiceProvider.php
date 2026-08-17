<?php

namespace App\Domain\Accounting\Providers;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Accounting\Models\Income;
use App\Domain\Accounting\Policies\ExpenseCategoryPolicy;
use App\Domain\Accounting\Policies\ExpensePolicy;
use App\Domain\Accounting\Policies\IncomePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccountingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
        Gate::policy(Income::class, IncomePolicy::class);
    }
}
