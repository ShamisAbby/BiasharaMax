<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Attribute;

class AttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attributes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attributes.create');
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $user->business_id === $attribute->business_id && $user->hasPermission('attributes.update');
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->business_id === $attribute->business_id && $user->hasPermission('attributes.delete');
    }
}
