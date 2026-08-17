<?php

namespace App\Domain\Payroll\Support;

use App\Domain\Authentication\Models\User;
use App\Domain\Payroll\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Model;

/**
 * The two questions every Payroll policy has to answer.
 *
 * Payroll is the one module where a record's subject and the person acting
 * on it are routinely different people, and where an employee legitimately
 * acts on their OWN record without holding any management permission — you
 * clock yourself out and request your own correction, but you must never
 * approve either. Keeping both checks here stops the distinction from
 * being re-derived (and eventually got wrong) in four separate policies.
 */
final class PayrollOwnership
{
    /**
     * Same-tenant check.
     *
     * Belt and braces. The Payroll models all use BelongsToTenant, whose
     * global scope already makes route-model binding 404 on another
     * business's record — so this is not the primary defence. It is here
     * because that scope is silently skipped in two cases the policies
     * still have to be right about: the `platform` guard bypasses it
     * deliberately, and any record reached other than through Eloquent
     * (a validated id from the request body, say) never passes through it
     * at all.
     */
    public static function sameBusiness(User $user, Model $record): bool
    {
        return $user->business_id !== null
            && $user->business_id === $record->getAttribute('business_id');
    }

    /**
     * Is this record about the calling user?
     *
     * Resolved through the employee profile rather than a `user_id` on the
     * record itself, because attendance and leave both hang off
     * EmployeeProfile — a user with no profile owns nothing, which is the
     * correct answer rather than an error.
     */
    public static function belongsToUser(User $user, Model $record): bool
    {
        $profileId = $record->getAttribute('employee_profile_id');

        if ($profileId === null) {
            return false;
        }

        return EmployeeProfile::query()
            ->whereKey($profileId)
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
