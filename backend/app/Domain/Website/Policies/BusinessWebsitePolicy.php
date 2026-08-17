<?php

namespace App\Domain\Website\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Website\Models\BusinessWebsite;

class BusinessWebsitePolicy
{
    public function view(User $user, BusinessWebsite $website): bool
    {
        return $user->business_id === $website->business_id && $user->hasPermission('website.view');
    }

    public function update(User $user, BusinessWebsite $website): bool
    {
        return $user->business_id === $website->business_id && $user->hasPermission('website.manage');
    }
}
