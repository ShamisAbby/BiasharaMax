<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Domain\Payroll\Models\LeaveType;
use App\Domain\Payroll\Services\LeaveService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function __construct(
        private readonly LeaveService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveType::class);

        $business = $request->user()->business;

        if ($business) {
            // Auto-seed default types if none exist
            if (LeaveType::query()->where('business_id', $business->id)->doesntExist()) {
                $this->service->seedDefaultLeaveTypes($business->id);
            }
        }

        $types = $business
            ? LeaveType::query()
                ->where('business_id', $business->id)
                ->withCount(['leaveRequests as total_requests', 'leaveRequests as pending_count' => fn ($q) => $q->where('status', 'pending')])
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('Payroll/Leave/Types', [
            'leaveTypes' => $types->values(),
            'canManage' => $request->user()->can('create', LeaveType::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveType::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'uppercase'],
            'color' => ['required', 'string'],
            'days_per_year' => ['required', 'integer', 'min:1', 'max:365'],
            'is_paid' => ['boolean'],
            'requires_approval' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'can_carry_forward' => ['boolean'],
            'max_carry_forward_days' => ['integer', 'min:0'],
            'min_notice_days' => ['integer', 'min:0'],
        ]);

        $business = $request->user()->business;

        LeaveType::create(array_merge($validated, ['business_id' => $business->id, 'is_system' => false]));

        return back()->with('success', 'Leave type created.');
    }

    /**
     * Leave types are shared configuration — changing one moves everybody's
     * entitlement — and this had no permission check at all.
     */
    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->authorize('update', $leaveType);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string'],
            'days_per_year' => ['required', 'integer', 'min:1', 'max:365'],
            'is_paid' => ['boolean'],
            'requires_approval' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'can_carry_forward' => ['boolean'],
            'max_carry_forward_days' => ['integer', 'min:0'],
            'min_notice_days' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $leaveType->update($validated);

        return back()->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $this->authorize('delete', $leaveType);

        if ($leaveType->is_system) {
            return back()->withErrors(['leave_type' => 'System leave types cannot be deleted.']);
        }

        $leaveType->delete();

        return back()->with('success', 'Leave type deleted.');
    }

    public function allocate(Request $request): RedirectResponse
    {
        $this->authorize('allocate', LeaveType::class);

        $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2099']]);

        $business = $request->user()->business;
        $count = $this->service->allocateLeaveForYear($business->id, (int) $request->input('year'));

        return back()->with('success', "Leave allocated for {$count} records.");
    }
}
