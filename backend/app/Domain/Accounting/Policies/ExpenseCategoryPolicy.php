<?php

namespace App\Domain\Accounting\Policies;

use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Authentication\Models\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.view');
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->business_id === $category->business_id && $user->hasPermission('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.expenses.manage');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->business_id === $category->business_id && $user->hasPermission('accounting.expenses.manage');
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $user->business_id === $category->business_id && $user->hasPermission('accounting.expenses.manage');
    }
}
