<?php

namespace App\Modules\RBAC\Policies;

use App\Modules\Authentication\Models\User;
use App\Modules\RBAC\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->sameBusiness($user, $role) && $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->sameBusiness($user, $role) && $user->hasPermission('roles.update');
    }

    /**
     * System role slugs (Owner, Manager, Cashier, Inventory Officer,
     * Accountant) can never be deleted, otherwise a business could be left
     * without a usable administrative role.
     */
    public function delete(User $user, Role $role): bool
    {
        return $this->sameBusiness($user, $role)
            && $user->hasPermission('roles.delete')
            && ! $role->is_system;
    }

    private function sameBusiness(User $user, Role $role): bool
    {
        return $user->business_id === $role->business_id;
    }
}
