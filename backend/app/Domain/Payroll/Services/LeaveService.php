<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Models\AttendanceRecord;
use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Models\LeaveBalance;
use App\Domain\Payroll\Models\LeaveRequest;
use App\Domain\Payroll\Models\LeaveType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    /**
     * Seed all default leave types for a new business.
     */
    public function seedDefaultLeaveTypes(string $businessId): void
    {
        $existing = LeaveType::query()->where('business_id', $businessId)->pluck('code')->all();

        foreach (LeaveType::DEFAULT_TYPES as $type) {
            if (in_array($type['code'], $existing, true)) {
                continue;
            }

            LeaveType::create(array_merge([
                'business_id' => $businessId,
                'requires_approval' => true,
                'can_carry_forward' => false,
                'is_active' => true,
                'is_system' => true,
                'gender_restricted' => false,
            ], $type));
        }
    }

    /**
     * Allocate leave balances for a given year for all active employees.
     */
    public function allocateLeaveForYear(string $businessId, int $year): int
    {
        $employees = EmployeeProfile::query()
            ->where('business_id', $businessId)
            ->where('status', EmployeeProfile::STATUS_ACTIVE)
            ->get();

        $types = LeaveType::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->get();

        $count = 0;

        foreach ($employees as $employee) {
            foreach ($types as $type) {
                LeaveBalance::firstOrCreate(
                    ['employee_profile_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                    [
                        'business_id' => $businessId,
                        'allocated_days' => $type->days_per_year,
                        'used_days' => 0,
                        'pending_days' => 0,
                        'carried_forward_days' => 0,
                        'available_days' => $type->days_per_year,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Submit a leave request and lock the pending balance.
     */
    public function submitRequest(EmployeeProfile $profile, array $data): LeaveRequest
    {
        $days = $this->calculateWorkingDays($data['start_date'], $data['end_date']);

        if (isset($data['is_half_day']) && $data['is_half_day']) {
            $days = 0.5;
        }

        $year = Carbon::parse($data['start_date'])->year;

        return DB::transaction(function () use ($profile, $data, $days, $year) {
            $request = LeaveRequest::create([
                'business_id' => $profile->business_id,
                'employee_profile_id' => $profile->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_requested' => $days,
                'is_half_day' => $data['is_half_day'] ?? false,
                'half_day_period' => $data['half_day_period'] ?? null,
                'status' => LeaveRequest::STATUS_PENDING,
                'reason' => $data['reason'],
            ]);

            // Lock the pending days in the balance
            $balance = $this->getOrCreateBalance($profile, $data['leave_type_id'], $year);
            $newPending = bcadd((string) $balance->pending_days, (string) $days, 2);
            $newAvailable = bcsub((string) $balance->available_days, (string) $days, 2);
            $balance->update([
                'pending_days' => $newPending,
                'available_days' => $newAvailable,
            ]);

            return $request;
        });
    }

    /**
     * Approve a leave request and create attendance records for the leave period.
     */
    public function approveRequest(LeaveRequest $request, string $userId, ?string $notes = null): void
    {
        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            throw new \RuntimeException('Only pending leave requests can be approved.');
        }

        DB::transaction(function () use ($request, $userId, $notes) {
            $request->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            // Move pending → used in balance
            $year = $request->start_date->year;
            $balance = $this->getOrCreateBalance($request->employeeProfile, $request->leave_type_id, $year);
            $days = (string) $request->days_requested;
            $balance->update([
                'pending_days' => bcsub((string) $balance->pending_days, $days, 2),
                'used_days' => bcadd((string) $balance->used_days, $days, 2),
            ]);

            // Create attendance records for each leave day
            $current = $request->start_date->copy();
            while ($current->lte($request->end_date)) {
                if (! $this->isWeekend($current)) {
                    AttendanceRecord::updateOrCreate(
                        [
                            'employee_profile_id' => $request->employee_profile_id,
                            'attendance_date' => $current->toDateString(),
                        ],
                        [
                            'business_id' => $request->business_id,
                            'status' => AttendanceRecord::STATUS_ON_LEAVE,
                            'day_type' => AttendanceRecord::DAY_REGULAR,
                            'leave_request_id' => $request->id,
                            'approved_by' => $userId,
                            'approved_at' => now(),
                        ]
                    );
                }
                $current->addDay();
            }
        });
    }

    /**
     * Reject a leave request and release the pending balance.
     */
    public function rejectRequest(LeaveRequest $request, string $userId, string $notes): void
    {
        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            throw new \RuntimeException('Only pending leave requests can be rejected.');
        }

        DB::transaction(function () use ($request, $userId, $notes) {
            $request->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            $year = $request->start_date->year;
            $balance = $this->getOrCreateBalance($request->employeeProfile, $request->leave_type_id, $year);
            $days = (string) $request->days_requested;
            $balance->update([
                'pending_days' => bcsub((string) $balance->pending_days, $days, 2),
                'available_days' => bcadd((string) $balance->available_days, $days, 2),
            ]);
        });
    }

    /**
     * Cancel a pending leave request.
     */
    public function cancelRequest(LeaveRequest $request): void
    {
        if (! in_array($request->status, [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED], true)) {
            throw new \RuntimeException('Cannot cancel this leave request.');
        }

        DB::transaction(function () use ($request) {
            $year = $request->start_date->year;
            $balance = $this->getOrCreateBalance($request->employeeProfile, $request->leave_type_id, $year);
            $days = (string) $request->days_requested;

            if ($request->status === LeaveRequest::STATUS_PENDING) {
                $balance->update([
                    'pending_days' => bcsub((string) $balance->pending_days, $days, 2),
                    'available_days' => bcadd((string) $balance->available_days, $days, 2),
                ]);
            } elseif ($request->status === LeaveRequest::STATUS_APPROVED) {
                $balance->update([
                    'used_days' => bcsub((string) $balance->used_days, $days, 2),
                    'available_days' => bcadd((string) $balance->available_days, $days, 2),
                ]);
                // Remove attendance records for the leave period
                AttendanceRecord::query()
                    ->where('leave_request_id', $request->id)
                    ->delete();
            }

            $request->update(['status' => LeaveRequest::STATUS_CANCELLED]);
        });
    }

    /**
     * Get all leave requests for a business (with optional filters).
     *
     * @return Collection<int, LeaveRequest>
     */
    public function requestsForBusiness(string $businessId, array $filters = []): Collection
    {
        $query = LeaveRequest::query()
            ->where('business_id', $businessId)
            ->with(['employeeProfile.user:id,name', 'leaveType:id,name,color,is_paid'])
            ->orderByDesc('created_at');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['employee_profile_id'])) {
            $query->where('employee_profile_id', $filters['employee_profile_id']);
        }

        return $query->get();
    }

    /**
     * Get leave balances for an employee in the current year.
     *
     * @return Collection<int, LeaveBalance>
     */
    public function balancesForEmployee(EmployeeProfile $profile, int $year): Collection
    {
        return LeaveBalance::query()
            ->where('employee_profile_id', $profile->id)
            ->where('year', $year)
            ->with('leaveType:id,name,color,is_paid,code')
            ->get();
    }

    /**
     * Calculate working days between two dates (Mon–Fri, skip weekends).
     */
    public function calculateWorkingDays(string $from, string $to): float
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        $days = 0;

        while ($start->lte($end)) {
            if (! $this->isWeekend($start)) {
                $days++;
            }
            $start->addDay();
        }

        return (float) $days;
    }

    private function getOrCreateBalance(EmployeeProfile $profile, string $leaveTypeId, int $year): LeaveBalance
    {
        $type = LeaveType::find($leaveTypeId);

        return LeaveBalance::firstOrCreate(
            ['employee_profile_id' => $profile->id, 'leave_type_id' => $leaveTypeId, 'year' => $year],
            [
                'business_id' => $profile->business_id,
                'allocated_days' => $type?->days_per_year ?? 0,
                'used_days' => 0,
                'pending_days' => 0,
                'carried_forward_days' => 0,
                'available_days' => $type?->days_per_year ?? 0,
            ]
        );
    }

    private function isWeekend(Carbon $date): bool
    {
        return $date->isWeekend();
    }
}
