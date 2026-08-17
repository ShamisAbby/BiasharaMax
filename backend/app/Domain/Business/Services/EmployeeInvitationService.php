<?php

namespace App\Domain\Business\Services;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Notifications\EmployeeInvitedNotification;
use Illuminate\Support\Arr;
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
     * @param  array{name: string, email: string, role_id?: ?string, role_ids?: list<string>, branch_id: ?string}  $data
     */
    public function invite(Business $business, User $invitedBy, array $data): User
    {
        $roleIds = array_values(array_filter(Arr::wrap($data['role_ids'] ?? $data['role_id'] ?? [])));

        $employee = User::query()->create([
            'business_id' => $business->getKey(),
            // Legacy single-role column — see the pivot sync below, which
            // is what actually grants permissions.
            'role_id' => $roleIds[0] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'invited_by' => $invitedBy->getKey(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(40)),
            'status' => User::STATUS_INVITED,
        ]);

        // The pivot is what grants permissions. Unlike the platform side,
        // an employee with no roles has none at all, so a missed sync
        // here locks them out rather than over-granting.
        $employee->roles()->sync($roleIds);

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
