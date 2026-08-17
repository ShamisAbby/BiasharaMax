<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Unit;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('units.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('units.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->business_id === $unit->business_id && $user->hasPermission('units.update');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->business_id === $unit->business_id && $user->hasPermission('units.delete');
    }
}
