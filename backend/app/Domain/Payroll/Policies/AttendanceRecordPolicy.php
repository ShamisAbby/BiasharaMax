<?php

namespace App\Domain\Payroll\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Payroll\Models\AttendanceRecord;
use App\Domain\Payroll\Support\PayrollOwnership;

class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('payroll.manage');
    }

    /**
     * Clocking out and the break controls all answer the same question:
     * may this person operate this timesheet right now?
     *
     * Ownership alone is enough — clocking yourself out is the normal case
     * and requires no permission. A supervisor may also do it for someone
     * who forgot, which is what `attendance.manage` is for. What neither
     * path allows is touching another business's record.
     */
    public function operate(User $user, AttendanceRecord $record): bool
    {
        if (! PayrollOwnership::sameBusiness($user, $record)) {
            return false;
        }

        return PayrollOwnership::belongsToUser($user, $record)
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('payroll.manage');
    }

    /**
     * Writing a record by hand bypasses the clock entirely, so it is
     * management-only regardless of whose record it is — including your own.
     */
    public function record(User $user): bool
    {
        return $user->hasPermission('attendance.manage') || $user->hasPermission('payroll.manage');
    }

    /**
     * Asking for a correction is a request, not a change; the approval step
     * is where the privilege lives. So an employee may raise one against
     * their own record with no permission at all.
     */
    public function requestCorrection(User $user, AttendanceRecord $record): bool
    {
        return $this->operate($user, $record);
    }
}
