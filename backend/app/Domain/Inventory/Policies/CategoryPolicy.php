<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Category;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->business_id === $category->business_id && $user->hasPermission('categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->business_id === $category->business_id && $user->hasPermission('categories.delete');
    }
}
