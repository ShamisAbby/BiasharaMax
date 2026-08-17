<?php

namespace App\Domain\Accounting\Policies;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Authentication\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->business_id === $expense->business_id && $user->hasPermission('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.expenses.manage');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->business_id === $expense->business_id && $user->hasPermission('accounting.expenses.manage');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->business_id === $expense->business_id && $user->hasPermission('accounting.expenses.manage');
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $user->business_id === $expense->business_id && $user->hasPermission('accounting.expenses.approve');
    }
}
