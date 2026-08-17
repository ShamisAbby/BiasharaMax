<?php

namespace App\Domain\Payroll\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Payroll\Models\LeaveRequest;
use App\Domain\Payroll\Support\PayrollOwnership;

class LeaveRequestPolicy
{
    /**
     * Everyone can reach the leave screen — it is where you apply for your
     * own leave. Seeing OTHER people's requests is `viewAny` below.
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return PayrollOwnership::sameBusiness($user, $leaveRequest)
            && (PayrollOwnership::belongsToUser($user, $leaveRequest)
                || $user->hasPermission('leave.view'));
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leave.view')
            || $user->hasPermission('leave.manage')
            || $user->hasPermission('payroll.manage');
    }

    public function create(User $user): bool
    {
        // Applying for your own leave is not a privileged action; anyone
        // with an employee profile may do it. The profile lookup in the
        // controller is what actually decides whether they can.
        return true;
    }

    /**
     * Approving and rejecting are the same decision in opposite directions,
     * so they share a rule.
     *
     * The self-approval bar is explicit. Without it an employee holding
     * `leave.approve` — a team lead, say — could sign off their own
     * request, which is exactly the control the approval step exists to
     * provide. They can still apply; someone else has to approve.
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return PayrollOwnership::sameBusiness($user, $leaveRequest)
            && ! PayrollOwnership::belongsToUser($user, $leaveRequest)
            && ($user->hasPermission('leave.approve') || $user->hasPermission('payroll.manage'));
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->approve($user, $leaveRequest);
    }

    /**
     * Cancelling your own pending request is ordinary self-service.
     * Cancelling someone else's is an administrative act.
     */
    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! PayrollOwnership::sameBusiness($user, $leaveRequest)) {
            return false;
        }

        return PayrollOwnership::belongsToUser($user, $leaveRequest)
            || $user->hasPermission('leave.manage')
            || $user->hasPermission('payroll.manage');
    }
}
