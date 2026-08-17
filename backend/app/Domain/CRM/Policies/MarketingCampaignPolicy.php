<?php

namespace App\Domain\CRM\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\CRM\Models\MarketingCampaign;

class MarketingCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, MarketingCampaign $campaign): bool
    {
        return $user->business_id === $campaign->business_id && $user->hasPermission('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, MarketingCampaign $campaign): bool
    {
        return $user->business_id === $campaign->business_id && $user->hasPermission('crm.manage');
    }

    public function delete(User $user, MarketingCampaign $campaign): bool
    {
        return $user->business_id === $campaign->business_id && $user->hasPermission('crm.manage');
    }

    public function send(User $user, MarketingCampaign $campaign): bool
    {
        return $user->business_id === $campaign->business_id && $user->hasPermission('crm.manage');
    }
}
