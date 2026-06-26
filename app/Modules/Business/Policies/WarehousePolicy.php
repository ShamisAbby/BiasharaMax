<?php

namespace App\Modules\Business\Policies;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('warehouses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->business_id === $warehouse->business_id && $user->hasPermission('warehouses.update');
    }

    /**
     * The default warehouse for a branch can never be deleted directly;
     * a different warehouse on the same branch must be promoted to default
     * first so stock-bearing modules always have somewhere to resolve to.
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->business_id === $warehouse->business_id
            && $user->hasPermission('warehouses.delete')
            && ! $warehouse->is_default;
    }
}
