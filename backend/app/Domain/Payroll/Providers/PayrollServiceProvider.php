<?php

namespace App\Domain\Payroll\Providers;

use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Models\PayrollPeriod;
use App\Domain\Payroll\Policies\EmployeeProfilePolicy;
use App\Domain\Payroll\Policies\PayrollPeriodPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PayrollServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(EmployeeProfile::class, EmployeeProfilePolicy::class);
        Gate::policy(PayrollPeriod::class, PayrollPeriodPolicy::class);
    }
}
