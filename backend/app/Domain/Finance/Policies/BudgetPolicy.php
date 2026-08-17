<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\Budget;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->business_id === $budget->business_id && $user->hasPermission('finance.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('finance.budgets.manage');
    }

    public function approve(User $user, Budget $budget): bool
    {
        return $user->business_id === $budget->business_id && $user->hasPermission('finance.budgets.approve');
    }

    public function activate(User $user, Budget $budget): bool
    {
        return $user->business_id === $budget->business_id && $user->hasPermission('finance.budgets.approve');
    }
}
