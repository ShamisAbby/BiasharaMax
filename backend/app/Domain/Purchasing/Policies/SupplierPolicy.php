<?php

namespace App\Domain\Purchasing\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Purchasing\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->business_id === $supplier->business_id && $user->hasPermission('suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->business_id === $supplier->business_id && $user->hasPermission('suppliers.delete');
    }
}
