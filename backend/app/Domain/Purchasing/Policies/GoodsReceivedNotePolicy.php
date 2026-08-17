<?php

namespace App\Domain\Purchasing\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Purchasing\Models\GoodsReceivedNote;

class GoodsReceivedNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('goods_received.view');
    }

    public function view(User $user, GoodsReceivedNote $note): bool
    {
        return $user->business_id === $note->business_id && $user->hasPermission('goods_received.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('goods_received.create');
    }
}
