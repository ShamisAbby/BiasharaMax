<?php

namespace App\Modules\Business\Policies;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Branch;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('branches.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->business_id === $branch->business_id && $user->hasPermission('branches.update');
    }

    /**
     * The main branch (created automatically at registration) can never be
     * deleted, otherwise warehouses, employees and future stock records
     * could be left without a location.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return $user->business_id === $branch->business_id
            && $user->hasPermission('branches.delete')
            && ! $branch->is_main;
    }
}
