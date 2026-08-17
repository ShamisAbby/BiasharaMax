<?php

namespace App\Domain\Sales\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Sales\Models\Sale;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->business_id === $sale->business_id && $user->hasPermission('sales.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.create');
    }

    public function void(User $user, Sale $sale): bool
    {
        return $user->business_id === $sale->business_id && $user->hasPermission('sales.void');
    }

    public function recordPayment(User $user, Sale $sale): bool
    {
        return $user->business_id === $sale->business_id && $user->hasPermission('sales.create');
    }
}
