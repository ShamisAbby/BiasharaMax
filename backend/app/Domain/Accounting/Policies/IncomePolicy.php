<?php

namespace App\Domain\Accounting\Policies;

use App\Domain\Accounting\Models\Income;
use App\Domain\Authentication\Models\User;

class IncomePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.view');
    }

    public function view(User $user, Income $income): bool
    {
        return $user->business_id === $income->business_id && $user->hasPermission('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.income.manage');
    }

    public function update(User $user, Income $income): bool
    {
        return $user->business_id === $income->business_id && $user->hasPermission('accounting.income.manage');
    }

    public function delete(User $user, Income $income): bool
    {
        return $user->business_id === $income->business_id && $user->hasPermission('accounting.income.manage');
    }
}
