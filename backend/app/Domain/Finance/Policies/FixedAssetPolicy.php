<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\FixedAsset;

class FixedAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, FixedAsset $asset): bool
    {
        return $user->business_id === $asset->business_id && $user->hasPermission('finance.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('finance.assets.manage');
    }

    public function depreciate(User $user): bool
    {
        return $user->hasPermission('finance.assets.depreciate');
    }

    public function dispose(User $user, FixedAsset $asset): bool
    {
        return $user->business_id === $asset->business_id && $user->hasPermission('finance.assets.manage');
    }
}
