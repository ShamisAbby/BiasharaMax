<?php

namespace App\Domain\Payroll\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Payroll\Models\EmployeeProfile;

class EmployeeProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.view');
    }

    public function view(User $user, EmployeeProfile $profile): bool
    {
        return $user->business_id === $profile->business_id && $user->hasPermission('payroll.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('payroll.manage');
    }
}
