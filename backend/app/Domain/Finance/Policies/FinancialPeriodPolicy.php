<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\FinancialPeriod;

class FinancialPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('finance.periods.manage');
    }

    public function close(User $user, FinancialPeriod $period): bool
    {
        return $user->business_id === $period->business_id && $user->hasPermission('finance.periods.close');
    }

    public function lock(User $user, FinancialPeriod $period): bool
    {
        return $user->business_id === $period->business_id && $user->hasPermission('finance.periods.manage');
    }
}
