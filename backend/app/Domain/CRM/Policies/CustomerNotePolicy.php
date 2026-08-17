<?php

namespace App\Domain\CRM\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\CRM\Models\CustomerNote;

class CustomerNotePolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function delete(User $user, CustomerNote $note): bool
    {
        return $user->business_id === $note->business_id && $user->hasPermission('crm.manage');
    }
}
