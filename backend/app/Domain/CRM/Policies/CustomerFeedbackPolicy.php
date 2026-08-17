<?php

namespace App\Domain\CRM\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\CRM\Models\CustomerFeedback;

class CustomerFeedbackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, CustomerFeedback $feedback): bool
    {
        return $user->business_id === $feedback->business_id && $user->hasPermission('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function manage(User $user, CustomerFeedback $feedback): bool
    {
        return $user->business_id === $feedback->business_id && $user->hasPermission('crm.manage');
    }
}
