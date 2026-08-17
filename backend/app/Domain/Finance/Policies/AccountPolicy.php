<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\Account;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, Account $account): bool
    {
        return $user->business_id === $account->business_id && $user->hasPermission('finance.view');
    }

    /**
     * CurrencyController authorizes every action with
     * `authorize('manage', Account::class)`, but this policy had no
     * `manage` method — and Laravel denies an ability a policy doesn't
     * define. That made Finance → Settings a guaranteed 403 for
     * everyone, including the business owner, with the generic "This
     * action is unauthorized" giving no clue that the ability simply
     * didn't exist.
     *
     * Gated on the same permission as create/update/delete below: the
     * currency screen edits finance configuration, so anyone who may
     * change the chart of accounts may change it. Every sibling policy
     * (Budget, FixedAsset, FinancialPeriod, TaxConfiguration) already
     * pairs its `manage` call with a matching method this way.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermission('finance.chart-of-accounts.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.chart-of-accounts.manage');
    }

    public function update(User $user, Account $account): bool
    {
        return $user->business_id === $account->business_id && $user->hasPermission('finance.chart-of-accounts.manage');
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->business_id === $account->business_id && $user->hasPermission('finance.chart-of-accounts.manage');
    }
}
