<?php

namespace App\Domain\CRM\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\CRM\Models\LoyaltyReward;

class LoyaltyRewardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, LoyaltyReward $reward): bool
    {
        return $user->business_id === $reward->business_id && $user->hasPermission('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, LoyaltyReward $reward): bool
    {
        return $user->business_id === $reward->business_id && $user->hasPermission('crm.manage');
    }

    public function delete(User $user, LoyaltyReward $reward): bool
    {
        return $user->business_id === $reward->business_id && $user->hasPermission('crm.manage');
    }
}
