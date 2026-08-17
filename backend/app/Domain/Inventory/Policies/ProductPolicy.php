<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->business_id === $product->business_id && $user->hasPermission('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->business_id === $product->business_id && $user->hasPermission('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->business_id === $product->business_id && $user->hasPermission('products.delete');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('products.import');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('products.export');
    }
}
