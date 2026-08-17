<?php

namespace App\Domain\Business\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $user->business_id === $business->getKey();
    }

    public function update(User $user, Business $business): bool
    {
        return $user->business_id === $business->getKey()
            && $user->hasPermission('business.update');
    }
}
