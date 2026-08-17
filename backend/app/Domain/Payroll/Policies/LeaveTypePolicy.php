<?php

namespace App\Domain\Payroll\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Payroll\Models\LeaveType;
use App\Domain\Payroll\Support\PayrollOwnership;

/**
 * Leave types are business configuration, not employee data — creating,
 * editing or deleting one changes everybody's entitlement, so all of it
 * sits behind `leave.manage`. Reading them is not gated here: the apply
 * form has to list the types you can apply for.
 */
class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leave.view')
            || $user->hasPermission('leave.manage')
            || $user->hasPermission('payroll.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('leave.manage') || $user->hasPermission('payroll.manage');
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return PayrollOwnership::sameBusiness($user, $leaveType)
            && ($user->hasPermission('leave.manage') || $user->hasPermission('payroll.manage'));
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $this->update($user, $leaveType);
    }

    /**
     * Allocating a year's entitlement writes a balance row for every
     * employee, so it is treated as management rather than configuration.
     */
    public function allocate(User $user): bool
    {
        return $user->hasPermission('leave.manage') || $user->hasPermission('payroll.manage');
    }
}
