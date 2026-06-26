<?php

namespace App\Modules\Business\Services;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Notifications\EmployeeInvitedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Invites a new employee into a business. The employee record is created
 * immediately (status: invited) with an unusable random password — we never
 * email a real password — and a signed, time-limited link is sent so the
 * employee can set their own password and activate the account.
 */
class EmployeeInvitationService
{
    /**
     * @param  array{name: string, email: string, role_id: string, branch_id: ?string}  $data
     */
    public function invite(Business $business, User $invitedBy, array $data): User
    {
        $employee = User::query()->create([
            'business_id' => $business->getKey(),
            'role_id' => $data['role_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'invited_by' => $invitedBy->getKey(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(40)),
            'status' => User::STATUS_INVITED,
        ]);

        $employee->notify(new EmployeeInvitedNotification($business->name, $invitedBy->name));

        return $employee;
    }

    public function activate(User $employee, string $password): void
    {
        $employee->forceFill([
            'password' => Hash::make($password),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ])->save();
    }
}
