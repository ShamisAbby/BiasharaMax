<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Models\LeaveRequest;
use App\Domain\Payroll\Models\LeaveType;
use App\Domain\Payroll\Services\LeaveService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(
        private readonly LeaveService $service,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $business = $user->business;

        // Was `$user->can('leave.view')`. A bare ability string goes to
        // Laravel's Gate, and this app has no gates or Payroll policies
        // registered for slugs — so it resolved to false for EVERYONE,
        // owner included. The effect was that nobody could see the team's
        // requests and the approve/reject buttons never rendered, while
        // the endpoints behind them were wide open. Authorization here is
        // `hasPermission()`, which reads the union across assigned roles.
        $canViewAll = $user->hasPermission('leave.view')
            || $user->hasPermission('leave.manage')
            || $user->hasPermission('payroll.manage');

        $canApprove = $user->hasPermission('leave.approve')
            || $user->hasPermission('payroll.manage');

        // My profile
        $myProfile = $business
            ? EmployeeProfile::query()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        // Leave requests
        $allRequests = $business
            ? $this->service->requestsForBusiness($business->id, array_filter([
                'status' => $request->input('status'),
                'employee_profile_id' => $canViewAll ? $request->input('employee_profile_id') : ($myProfile?->id),
            ]))
            : collect();

        // My balances
        $myBalances = $myProfile
            ? $this->service->balancesForEmployee($myProfile, (int) now()->format('Y'))
            : collect();

        // Leave types for the apply form
        $leaveTypes = $business
            ? LeaveType::query()
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'color', 'days_per_year', 'is_paid', 'min_notice_days', 'requires_attachment'])
            : collect();

        $employees = ($business && $canViewAll)
            ? EmployeeProfile::query()
                ->where('business_id', $business->id)
                ->where('status', EmployeeProfile::STATUS_ACTIVE)
                ->with('user:id,name')
                ->orderBy('employee_number')
                ->get(['id', 'user_id', 'employee_number', 'department'])
            : collect();

        return Inertia::render('Payroll/Leave/Index', [
            'requests'    => $allRequests->values(),
            'balances'    => $myBalances->values(),
            'leaveTypes'  => $leaveTypes->values(),
            'employees'   => $employees->values(),
            'myProfileId' => $myProfile?->id,
            'canViewAll'  => $canViewAll,
            'canApprove'  => $canApprove,
            'filters'     => [
                'status'              => $request->input('status', ''),
                'employee_profile_id' => $request->input('employee_profile_id', ''),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $business = $user->business;

        $request->validate([
            // Scoped to the caller's own business: without this a UUID from
            // another tenant's leave-type list would be accepted and the
            // request written against it.
            'leave_type_id' => [
                'required',
                'uuid',
                Rule::exists(LeaveType::class, 'id')->where('business_id', $business?->id),
            ],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:10'],
            'is_half_day' => ['boolean'],
            'half_day_period' => ['nullable', 'in:morning,afternoon'],
        ]);

        $profile = EmployeeProfile::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $this->service->submitRequest($profile, $request->all());

            return back()->with('success', 'Leave request submitted successfully.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }
    }

    /**
     * The policy — not this method — is what stops an employee approving
     * their own request.
     *
     * These three actions previously had no check whatsoever: the only
     * condition on approval was `status === pending`, so anyone who could
     * reach the route could sign off their own leave. Tenancy was already
     * covered by the model's global scope; authorization was not.
     */
    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('approve', $leaveRequest);

        $request->validate(['notes' => ['nullable', 'string']]);

        try {
            $this->service->approveRequest($leaveRequest, $request->user()->id, $request->input('notes'));

            return back()->with('success', 'Leave request approved.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('reject', $leaveRequest);

        $request->validate(['approval_notes' => ['required', 'string', 'min:5']]);

        try {
            $this->service->rejectRequest($leaveRequest, $request->user()->id, $request->input('approval_notes'));

            return back()->with('success', 'Leave request rejected.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }
    }

    public function destroy(LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('delete', $leaveRequest);

        try {
            $this->service->cancelRequest($leaveRequest);

            return back()->with('success', 'Leave request cancelled.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }
    }
}
