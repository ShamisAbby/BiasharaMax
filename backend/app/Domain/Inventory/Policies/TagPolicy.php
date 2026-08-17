<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Tag;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tags.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tags.create');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->business_id === $tag->business_id && $user->hasPermission('tags.update');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->business_id === $tag->business_id && $user->hasPermission('tags.delete');
    }
}
