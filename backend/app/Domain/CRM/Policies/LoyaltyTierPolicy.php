<?php

namespace App\Domain\CRM\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\CRM\Models\LoyaltyTier;

class LoyaltyTierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, LoyaltyTier $tier): bool
    {
        return $user->business_id === $tier->business_id && $user->hasPermission('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, LoyaltyTier $tier): bool
    {
        return $user->business_id === $tier->business_id && $user->hasPermission('crm.manage');
    }

    public function delete(User $user, LoyaltyTier $tier): bool
    {
        return $user->business_id === $tier->business_id && $user->hasPermission('crm.manage');
    }
}
