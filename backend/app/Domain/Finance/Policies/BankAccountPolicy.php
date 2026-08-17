<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\BankAccount;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->business_id === $bankAccount->business_id && $user->hasPermission('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.bank-accounts.manage');
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->business_id === $bankAccount->business_id && $user->hasPermission('finance.bank-accounts.manage');
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $user->business_id === $bankAccount->business_id && $user->hasPermission('finance.bank-accounts.manage');
    }

    public function reconcile(User $user, BankAccount $bankAccount): bool
    {
        return $user->business_id === $bankAccount->business_id && $user->hasPermission('finance.bank-accounts.reconcile');
    }
}
