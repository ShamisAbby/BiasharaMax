<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\TaxConfiguration;

class TaxConfigurationPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('finance.tax.returns.view') || $user->hasPermission('finance.tax.manage');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('finance.tax.manage');
    }
}
