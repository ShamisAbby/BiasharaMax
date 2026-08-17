<?php

namespace App\Domain\Sales\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Sales\Models\SaleReturn;

class SaleReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales_returns.view');
    }

    public function view(User $user, SaleReturn $saleReturn): bool
    {
        return $user->business_id === $saleReturn->business_id && $user->hasPermission('sales_returns.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_returns.create');
    }

    public function approve(User $user, SaleReturn $saleReturn): bool
    {
        return $user->business_id === $saleReturn->business_id && $user->hasPermission('sales_returns.approve');
    }
}
