<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Models\AttendanceRecord;
use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Models\LeaveRequest;
use App\Domain\Payroll\Models\PayrollPeriod;
use Illuminate\Support\Carbon;

class HrDashboardService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * Full HR dashboard summary.
     *
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $dailyStats = $this->attendanceService->dailyStats($businessId, $today);
        $overtimeHours = $this->attendanceService->totalOvertimeHours($businessId, $monthStart, $monthEnd);
        $attendanceTrend = $this->attendanceService->attendanceTrend($businessId, 7);

        $pendingLeave = LeaveRequest::query()
            ->where('business_id', $businessId)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();

        $currentPayroll = PayrollPeriod::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [PayrollPeriod::STATUS_DRAFT, PayrollPeriod::STATUS_PROCESSING, PayrollPeriod::STATUS_APPROVED])
            ->orderByDesc('period_start')
            ->first();

        $lastPaidPayroll = PayrollPeriod::query()
            ->where('business_id', $businessId)
            ->where('status', PayrollPeriod::STATUS_PAID)
            ->orderByDesc('paid_at')
            ->first();

        $upcomingBirthdays = EmployeeProfile::query()
            ->where('business_id', $businessId)
            ->where('status', EmployeeProfile::STATUS_ACTIVE)
            ->whereNotNull('birth_date')
            ->with('user:id,name')
            ->get()
            ->filter(function (EmployeeProfile $e) {
                $birthday = Carbon::parse($e->birth_date)->setYear((int) now()->format('Y'));
                if ($birthday->isPast()) {
                    $birthday->addYear();
                }

                return $birthday->diffInDays(now()) <= 14;
            })
            ->map(fn (EmployeeProfile $e) => [
                'name' => $e->user?->name,
                'birth_date' => $e->birth_date?->format('M d'),
            ])
            ->values()
            ->all();

        $upcomingContractExpiry = EmployeeProfile::query()
            ->where('business_id', $businessId)
            ->where('status', EmployeeProfile::STATUS_ACTIVE)
            ->whereNotNull('contract_end_date')
            ->where('contract_end_date', '<=', now()->addDays(30)->toDateString())
            ->where('contract_end_date', '>=', now()->toDateString())
            ->with('user:id,name')
            ->get()
            ->map(fn (EmployeeProfile $e) => [
                'name' => $e->user?->name,
                'contract_end_date' => $e->contract_end_date?->toDateString(),
                'days_remaining' => now()->diffInDays($e->contract_end_date),
            ])
            ->values()
            ->all();

        $departmentStats = EmployeeProfile::query()
            ->where('business_id', $businessId)
            ->where('status', EmployeeProfile::STATUS_ACTIVE)
            ->whereNotNull('department')
            ->selectRaw('department, count(*) as count')
            ->groupBy('department')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['department' => $r->department, 'count' => (int) $r->count])
            ->all();

        return [
            'attendance' => $dailyStats,
            'overtime_hours_this_month' => $overtimeHours,
            'attendance_trend' => $attendanceTrend,
            'pending_leave_requests' => $pendingLeave,
            'current_payroll' => $currentPayroll ? [
                'id' => $currentPayroll->id,
                'period_name' => $currentPayroll->period_name,
                'status' => $currentPayroll->status,
                'total_net' => (float) $currentPayroll->total_net,
            ] : null,
            'last_paid_payroll' => $lastPaidPayroll ? [
                'period_name' => $lastPaidPayroll->period_name,
                'total_net' => (float) $lastPaidPayroll->total_net,
                'paid_at' => $lastPaidPayroll->paid_at?->toDateString(),
            ] : null,
            'upcoming_birthdays' => $upcomingBirthdays,
            'upcoming_contract_expiry' => $upcomingContractExpiry,
            'department_stats' => $departmentStats,
        ];
    }
}
