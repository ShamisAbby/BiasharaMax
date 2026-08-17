<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\InventoryCount;

class InventoryCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory_counts.view');
    }

    public function view(User $user, InventoryCount $count): bool
    {
        return $user->business_id === $count->business_id && $user->hasPermission('inventory_counts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory_counts.create');
    }

    public function complete(User $user, InventoryCount $count): bool
    {
        return $user->business_id === $count->business_id && $user->hasPermission('inventory_counts.complete');
    }
}
