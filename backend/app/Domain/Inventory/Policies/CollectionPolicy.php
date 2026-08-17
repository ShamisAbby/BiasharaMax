<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Collection;

class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('collections.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('collections.create');
    }

    public function update(User $user, Collection $collection): bool
    {
        return $user->business_id === $collection->business_id && $user->hasPermission('collections.update');
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $user->business_id === $collection->business_id && $user->hasPermission('collections.delete');
    }
}
