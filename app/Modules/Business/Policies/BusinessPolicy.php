<?php

namespace App\Modules\Business\Policies;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Business;

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
