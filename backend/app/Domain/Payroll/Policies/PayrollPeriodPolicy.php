<?php

namespace App\Domain\Payroll\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Payroll\Models\PayrollPeriod;

class PayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.view');
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return $user->business_id === $period->business_id && $user->hasPermission('payroll.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('payroll.manage');
    }

    public function approve(User $user, PayrollPeriod $period): bool
    {
        return $user->business_id === $period->business_id && $user->hasPermission('payroll.approve');
    }

    public function pay(User $user, PayrollPeriod $period): bool
    {
        return $user->business_id === $period->business_id && $user->hasPermission('payroll.process');
    }
}
