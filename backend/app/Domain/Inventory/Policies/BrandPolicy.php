<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Brand;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('brands.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('brands.create');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->business_id === $brand->business_id && $user->hasPermission('brands.update');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->business_id === $brand->business_id && $user->hasPermission('brands.delete');
    }
}
