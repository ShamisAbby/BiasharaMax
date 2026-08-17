<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Models\AttendanceCorrection;
use App\Domain\Payroll\Models\AttendanceRecord;
use App\Domain\Payroll\Models\AttendanceShift;
use App\Domain\Payroll\Models\EmployeeProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Record clock-in for an employee.
     * Detects late arrival based on active shift + grace period.
     */
    public function clockIn(EmployeeProfile $profile, array $data = []): AttendanceRecord
    {
        $date = Carbon::today()->toDateString();

        $existing = AttendanceRecord::query()
            ->where('employee_profile_id', $profile->id)
            ->where('attendance_date', $date)
            ->first();

        if ($existing && $existing->clock_in_at !== null) {
            throw new \RuntimeException('Already clocked in for today.');
        }

        $now = now();
        $shift = $this->activeShiftFor($profile);

        $isLate = false;
        $lateMinutes = 0;

        if ($shift) {
            $shiftStart = Carbon::today()->setTimeFromTimeString($shift->start_time);
            $deadline = $shiftStart->copy()->addMinutes($shift->grace_minutes);
            if ($now->gt($deadline)) {
                $isLate = true;
                $lateMinutes = (int) $now->diffInMinutes($shiftStart);
            }
        }

        if ($existing) {
            $existing->update([
                'clock_in_at' => $now,
                'is_late' => $isLate,
                'late_minutes' => $lateMinutes,
                'status' => $isLate ? AttendanceRecord::STATUS_LATE : AttendanceRecord::STATUS_PRESENT,
                'clock_in_method' => $data['method'] ?? AttendanceRecord::METHOD_MANUAL,
                'location_latitude' => $data['latitude'] ?? null,
                'location_longitude' => $data['longitude'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            return $existing->refresh();
        }

        return AttendanceRecord::create([
            'business_id' => $profile->business_id,
            'employee_profile_id' => $profile->id,
            'shift_id' => $shift?->id,
            'attendance_date' => $date,
            'day_type' => AttendanceRecord::DAY_REGULAR,
            'status' => $isLate ? AttendanceRecord::STATUS_LATE : AttendanceRecord::STATUS_PRESENT,
            'clock_in_at' => $now,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
            'clock_in_method' => $data['method'] ?? AttendanceRecord::METHOD_MANUAL,
            'location_latitude' => $data['latitude'] ?? null,
            'location_longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Record clock-out and calculate worked hours + overtime.
     */
    public function clockOut(AttendanceRecord $record, array $data = []): AttendanceRecord
    {
        if ($record->clock_in_at === null) {
            throw new \RuntimeException('Cannot clock out — no clock-in recorded.');
        }

        if ($record->clock_out_at !== null) {
            throw new \RuntimeException('Already clocked out.');
        }

        $now = now();
        $totalMinutes = $record->clock_in_at->diffInMinutes($now);
        $breakMinutes = $this->breakMinutes($record);
        $workedMinutes = max(0, $totalMinutes - $breakMinutes);
        $regularHours = round($workedMinutes / 60, 2);

        $expectedHours = $record->shift?->expected_hours ?? 8;
        $overtimeHours = max(0, round($regularHours - $expectedHours, 2));
        $netHours = min($regularHours, (float) $expectedHours);

        $earlyDeparture = false;
        if ($record->shift) {
            $shiftEnd = Carbon::today()->setTimeFromTimeString($record->shift->end_time);
            if ($record->shift->is_overnight) {
                $shiftEnd->addDay();
            }
            $earlyDeparture = $now->lt($shiftEnd);
        }

        $record->update([
            'clock_out_at' => $now,
            'regular_hours' => $netHours,
            'overtime_hours' => $overtimeHours,
            'break_hours' => round($breakMinutes / 60, 2),
            'early_departure' => $earlyDeparture,
            'notes' => $data['notes'] ?? $record->notes,
        ]);

        return $record->refresh();
    }

    public function startBreak(AttendanceRecord $record): AttendanceRecord
    {
        if ($record->clock_in_at === null) {
            throw new \RuntimeException('Not clocked in yet.');
        }
        if ($record->break_start_at !== null && $record->break_end_at === null) {
            throw new \RuntimeException('Already on a break.');
        }

        $record->update(['break_start_at' => now(), 'break_end_at' => null]);

        return $record->refresh();
    }

    public function endBreak(AttendanceRecord $record): AttendanceRecord
    {
        if ($record->break_start_at === null) {
            throw new \RuntimeException('No break started.');
        }

        $record->update(['break_end_at' => now()]);

        return $record->refresh();
    }

    /**
     * HR/Manager creates a manual attendance record for any employee and date.
     */
    public function manualRecord(string $businessId, string $employeeProfileId, array $data): AttendanceRecord
    {
        $date = $data['attendance_date'];

        $existing = AttendanceRecord::query()
            ->where('employee_profile_id', $employeeProfileId)
            ->where('attendance_date', $date)
            ->first();

        $payload = array_merge([
            'business_id' => $businessId,
            'employee_profile_id' => $employeeProfileId,
            'attendance_date' => $date,
            'day_type' => AttendanceRecord::DAY_REGULAR,
            'status' => AttendanceRecord::STATUS_PRESENT,
            'clock_in_method' => AttendanceRecord::METHOD_MANUAL,
        ], $data);

        if ($existing) {
            $existing->update($payload);

            return $existing->refresh();
        }

        return AttendanceRecord::create($payload);
    }

    /**
     * Submit a correction request for an attendance record.
     */
    public function submitCorrection(AttendanceRecord $record, array $data): AttendanceCorrection
    {
        $existing = $record->correction;
        if ($existing && $existing->status === AttendanceCorrection::STATUS_PENDING) {
            throw new \RuntimeException('A pending correction already exists for this record.');
        }

        return AttendanceCorrection::create([
            'business_id' => $record->business_id,
            'attendance_record_id' => $record->id,
            'employee_profile_id' => $record->employee_profile_id,
            'requested_clock_in' => $data['requested_clock_in'] ?? null,
            'requested_clock_out' => $data['requested_clock_out'] ?? null,
            'reason' => $data['reason'],
            'status' => AttendanceCorrection::STATUS_PENDING,
        ]);
    }

    /**
     * Approve a correction and apply the corrected times to the attendance record.
     */
    public function approveCorrection(AttendanceCorrection $correction, string $userId): void
    {
        DB::transaction(function () use ($correction, $userId) {
            $correction->update([
                'status' => AttendanceCorrection::STATUS_APPROVED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            $record = $correction->attendanceRecord;
            $updates = [];

            if ($correction->requested_clock_in) {
                $updates['clock_in_at'] = $correction->requested_clock_in;
            }
            if ($correction->requested_clock_out) {
                $updates['clock_out_at'] = $correction->requested_clock_out;
            }
            if ($updates) {
                $updates['approved_by'] = $userId;
                $updates['approved_at'] = now();
                $record->update($updates);

                // Recalculate hours if both times are present
                if ($record->clock_in_at && $record->clock_out_at) {
                    $record->refresh();
                    $this->clockOut($record->fresh());
                }
            }
        });
    }

    public function rejectCorrection(AttendanceCorrection $correction, string $userId, string $notes): void
    {
        $correction->update([
            'status' => AttendanceCorrection::STATUS_REJECTED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'reviewer_notes' => $notes,
        ]);
    }

    /**
     * Get daily attendance stats for the HR dashboard.
     *
     * @return array<string, int|float>
     */
    public function dailyStats(string $businessId, string $date): array
    {
        $total = EmployeeProfile::query()->where('business_id', $businessId)->where('status', EmployeeProfile::STATUS_ACTIVE)->count();

        $records = AttendanceRecord::query()
            ->where('business_id', $businessId)
            ->where('attendance_date', $date)
            ->get();

        $present = $records->whereIn('status', [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE])->count();
        $late = $records->where('status', AttendanceRecord::STATUS_LATE)->count();
        $onLeave = $records->where('status', AttendanceRecord::STATUS_ON_LEAVE)->count();
        $absent = $total - $present - $onLeave;

        return [
            'total' => $total,
            'present' => $present,
            'absent' => max(0, $absent),
            'late' => $late,
            'on_leave' => $onLeave,
            'attendance_pct' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get attendance records for a given business and date range (for the list view).
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function recordsForPeriod(string $businessId, string $from, string $to, ?string $employeeId = null): Collection
    {
        $query = AttendanceRecord::query()
            ->where('business_id', $businessId)
            ->whereBetween('attendance_date', [$from, $to])
            ->with(['employeeProfile.user:id,name', 'shift:id,name']);

        if ($employeeId) {
            $query->where('employee_profile_id', $employeeId);
        }

        return $query->orderByDesc('attendance_date')->orderBy('employee_profile_id')->get();
    }

    /**
     * Overtime hours in a period for the HR dashboard.
     */
    public function totalOvertimeHours(string $businessId, string $from, string $to): float
    {
        return (float) AttendanceRecord::query()
            ->where('business_id', $businessId)
            ->whereBetween('attendance_date', [$from, $to])
            ->sum('overtime_hours');
    }

    /**
     * Attendance trend — last N days for the line chart.
     *
     * @return array<int, array{date: string, present: int, absent: int, late: int}>
     */
    public function attendanceTrend(string $businessId, int $days = 7): array
    {
        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $stats = $this->dailyStats($businessId, $date);
            $trend[] = ['date' => $date, 'present' => $stats['present'], 'absent' => $stats['absent'], 'late' => $stats['late']];
        }

        return $trend;
    }

    /**
     * Fetch active shift for an employee (or the default shift for the business).
     */
    private function activeShiftFor(EmployeeProfile $profile): ?AttendanceShift
    {
        return AttendanceShift::query()
            ->where('business_id', $profile->business_id)
            ->where('is_active', true)
            ->first();
    }

    private function breakMinutes(AttendanceRecord $record): int
    {
        if ($record->break_start_at === null) {
            return 0;
        }
        $breakEnd = $record->break_end_at ?? now();

        return (int) $record->break_start_at->diffInMinutes($breakEnd);
    }
}
