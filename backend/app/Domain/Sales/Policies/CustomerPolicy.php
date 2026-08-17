<?php

namespace App\Domain\Sales\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Sales\Models\Customer;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->business_id === $customer->business_id && $user->hasPermission('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->business_id === $customer->business_id && $user->hasPermission('customers.update');
    }

    /**
     * Gates CRM-side actions on a customer (notes, tags, group
     * assignment, loyalty adjustments) — deliberately separate from
     * `customers.update` so a Customer Support role can manage CRM
     * data without the broader sales-side edit permission.
     */
    public function manageCrm(User $user, Customer $customer): bool
    {
        return $user->business_id === $customer->business_id && $user->hasPermission('crm.manage');
    }
}
