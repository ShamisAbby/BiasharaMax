<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Domain\Payroll\Models\AttendanceCorrection;
use App\Domain\Payroll\Models\AttendanceRecord;
use App\Domain\Payroll\Models\AttendanceShift;
use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Services\AttendanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $service,
    ) {}

    public function index(Request $request): Response
    {
        // Deliberately NOT gated as a whole: this page is also where an
        // ordinary employee clocks in and out, so requiring `attendance.view`
        // to open it would lock everyone without a management permission out
        // of their own time clock. The roster below is what gets scoped
        // instead — you always see yourself, and the rest of the team only
        // if you are allowed to.
        $user     = $request->user();
        $business = $user->business;
        $date     = $request->input('date', today()->toDateString());

        $canViewAll = $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('payroll.manage');

        // The current user's own profile — needed both for the clock-in
        // controls and to scope the roster when they can't see everyone.
        $profile = $business
            ? EmployeeProfile::query()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        $hasProfile = $profile !== null;

        $records = $business
            ? $this->service->recordsForPeriod($business->id, $date, $date)
            : collect();

        if (! $canViewAll) {
            // Colleagues' hours, lateness and absences are not something
            // every employee should be able to read off the roster.
            $records = $records->where('employee_profile_id', $profile?->id)->values();
        }

        // Business-wide attendance percentages are a management figure, so
        // they follow the same rule as the roster rather than leaking the
        // shape of the team to everyone.
        $stats = ($business && $canViewAll)
            ? $this->service->dailyStats($business->id, $date)
            : ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'on_leave' => 0, 'attendance_pct' => 0];

        // Only used to populate the manual-entry picker, which is itself
        // management-only — so there is no reason to ship the staff list to
        // anyone else.
        $employees = ($business && $canViewAll)
            ? EmployeeProfile::query()
                ->where('business_id', $business->id)
                ->where('status', EmployeeProfile::STATUS_ACTIVE)
                ->with('user:id,name')
                ->orderBy('employee_number')
                ->get(['id', 'employee_number', 'user_id', 'department', 'position'])
            : collect();

        // The current user's open attendance record for today (clock-in/out controls)
        $openRecord = $profile
            ? AttendanceRecord::query()
                ->where('employee_profile_id', $profile->id)
                ->whereDate('attendance_date', today())
                ->whereNotNull('clock_in_at')
                ->whereNull('clock_out_at')
                ->first()
            : null;

        return Inertia::render('Payroll/Attendance/Index', [
            'records'    => $records->values(),
            'stats'      => $stats,
            'date'       => $date,
            'employees'  => $employees->values(),
            // Were `$user->can('payroll.manage')` / `can('payroll.approve')`.
            // A bare ability string is resolved by Laravel's Gate, and no
            // gate or Payroll policy is registered for these slugs — so both
            // were false for everyone, owner included, and the manage and
            // approve controls never rendered for anybody. Authorization in
            // this app is `hasPermission()`.
            'canManage'  => $user->hasPermission('attendance.manage')
                || $user->hasPermission('payroll.manage'),
            'canApprove' => $user->hasPermission('attendance.approve')
                || $user->hasPermission('attendance.manage')
                || $user->hasPermission('payroll.manage'),
            'canViewAll' => $canViewAll,
            'openRecord' => $openRecord,
            'hasProfile' => $hasProfile,
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $user = $request->user();
        $business = $user->business;

        $profile = EmployeeProfile::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            return back()->withErrors(['clock_in' => 'You do not have an employee profile. Ask your administrator to set one up.']);
        }

        try {
            $this->service->clockIn($profile, [
                'method' => $request->input('method', AttendanceRecord::METHOD_MANUAL),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'notes' => $request->input('notes'),
                'created_by' => $user->id,
            ]);

            return back()->with('success', 'Clocked in successfully.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['clock_in' => $e->getMessage()]);
        }
    }

    public function clockOut(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $this->authorize('operate', $record);

        try {
            $this->service->clockOut($record, ['notes' => $request->input('notes')]);

            return back()->with('success', 'Clocked out successfully.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['clock_out' => $e->getMessage()]);
        }
    }

    public function startBreak(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $this->authorize('operate', $record);

        try {
            $this->service->startBreak($record);

            return back()->with('success', 'Break started.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['break' => $e->getMessage()]);
        }
    }

    public function endBreak(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $this->authorize('operate', $record);

        try {
            $this->service->endBreak($record);

            return back()->with('success', 'Break ended.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['break' => $e->getMessage()]);
        }
    }

    public function manualRecord(Request $request): RedirectResponse
    {
        // Writing hours by hand bypasses the clock entirely and feeds
        // straight into payroll, so it is management-only — including for
        // your own record.
        $this->authorize('record', AttendanceRecord::class);

        $business = $request->user()->business;

        $request->validate([
            // `['required', 'uuid']` alone was the hole: any well-formed
            // UUID passed, including an employee profile belonging to a
            // different business. The record was then written under the
            // CALLER's business_id while pointing at another tenant's
            // profile, corrupting both.
            //
            // The model's BelongsToTenant scope does NOT cover this — that
            // only applies to Eloquent queries, and this id arrives in the
            // request body. `Rule::exists` goes through the presence
            // verifier, which bypasses global scopes too, so the
            // business_id condition has to be written out explicitly.
            'employee_profile_id' => [
                'required',
                'uuid',
                Rule::exists(EmployeeProfile::class, 'id')->where('business_id', $business?->id),
            ],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(AttendanceRecord::statuses())],
            'clock_in_at' => ['nullable', 'date_format:H:i'],
            'clock_out_at' => ['nullable', 'date_format:H:i'],
        ]);

        $clockIn = $request->input('clock_in_at')
            ? $request->input('attendance_date') . ' ' . $request->input('clock_in_at') . ':00'
            : null;

        $clockOut = $request->input('clock_out_at')
            ? $request->input('attendance_date') . ' ' . $request->input('clock_out_at') . ':00'
            : null;

        $this->service->manualRecord($business->id, $request->input('employee_profile_id'), [
            'attendance_date' => $request->input('attendance_date'),
            'status' => $request->input('status'),
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'notes' => $request->input('notes'),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Attendance recorded.');
    }

    public function corrections(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceCorrection::class);

        $business = $request->user()->business;

        $corrections = $business
            ? AttendanceCorrection::query()
                ->where('business_id', $business->id)
                ->with([
                    'employeeProfile.user:id,name',
                    'attendanceRecord:id,attendance_date,clock_in_at,clock_out_at',
                    'reviewer:id,name',
                ])
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return Inertia::render('Payroll/Attendance/Corrections', [
            'corrections' => $corrections->values(),
        ]);
    }

    public function storeCorrection(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $this->authorize('requestCorrection', $record);

        $request->validate([
            'reason' => ['required', 'string', 'min:10'],
            'requested_clock_in' => ['nullable', 'date_format:H:i'],
            'requested_clock_out' => ['nullable', 'date_format:H:i'],
        ]);

        $date = $record->attendance_date->toDateString();

        $this->service->submitCorrection($record, [
            'reason' => $request->input('reason'),
            'requested_clock_in' => $request->input('requested_clock_in')
                ? $date . ' ' . $request->input('requested_clock_in') . ':00'
                : null,
            'requested_clock_out' => $request->input('requested_clock_out')
                ? $date . ' ' . $request->input('requested_clock_out') . ':00'
                : null,
        ]);

        return back()->with('success', 'Correction request submitted.');
    }

    public function approveCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $this->authorize('review', $correction);

        $this->service->approveCorrection($correction, $request->user()->id);

        return back()->with('success', 'Correction approved and applied.');
    }

    public function rejectCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $this->authorize('review', $correction);

        $request->validate(['notes' => ['required', 'string']]);

        $this->service->rejectCorrection($correction, $request->user()->id, $request->input('notes'));

        return back()->with('success', 'Correction rejected.');
    }
}
