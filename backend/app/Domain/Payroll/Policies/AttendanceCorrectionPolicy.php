<?php

namespace App\Domain\Payroll\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Payroll\Models\AttendanceCorrection;
use App\Domain\Payroll\Support\PayrollOwnership;

class AttendanceCorrectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.approve')
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('payroll.manage');
    }

    /**
     * A correction rewrites recorded hours, which feed payroll — so this is
     * the single most sensitive action in the module.
     *
     * Self-approval is barred for the same reason as leave: an employee who
     * could approve their own correction could pay themselves for hours
     * they did not work, and the request/approve split would be theatre.
     */
    public function review(User $user, AttendanceCorrection $correction): bool
    {
        return PayrollOwnership::sameBusiness($user, $correction)
            && ! PayrollOwnership::belongsToUser($user, $correction)
            && ($user->hasPermission('attendance.approve')
                || $user->hasPermission('attendance.manage')
                || $user->hasPermission('payroll.manage'));
    }
}
