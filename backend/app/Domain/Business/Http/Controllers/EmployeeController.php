<?php

namespace App\Domain\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Http\Requests\EmployeeInviteRequest;
use App\Domain\Business\Http\Requests\EmployeeUpdateRequest;
use App\Domain\Business\Http\Resources\BranchResource;
use App\Domain\Business\Http\Resources\EmployeeResource;
use App\Domain\Business\Services\EmployeeInvitationService;
use App\Domain\RBAC\Http\Resources\RoleResource;
use App\Domain\RBAC\Models\Role;
use App\Domain\Subscription\Exceptions\PlanLimitExceededException;
use App\Domain\Subscription\Services\SubscriptionLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeInvitationService $invitationService,
        private readonly SubscriptionLimitService $limits,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('employees.view'), 403);

        $business = $request->user()->business;

        $employees = User::query()
            ->where('business_id', $business->getKey())
            ->with(['roles', 'branch', 'business'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings/Employees', [
            'employees' => EmployeeResource::collection($employees),
            'roles' => RoleResource::collection(
                Role::query()->where('business_id', $business->getKey())->orderBy('name')->get()
            ),
            'branches' => BranchResource::collection(
                $business->branches()->where('status', 'active')->orderBy('name')->get()
            ),
        ]);
    }

    public function store(EmployeeInviteRequest $request): RedirectResponse
    {
        try {
            $this->limits->ensureCanAdd($request->user()->business, 'employees');
        } catch (PlanLimitExceededException $e) {
            return back()->withErrors(['employee' => $e->getMessage()]);
        }

        $this->invitationService->invite(
            $request->user()->business,
            $request->user(),
            $request->validated(),
        );

        return back()->with('status', 'employee-invited');
    }

    public function update(EmployeeUpdateRequest $request, User $employee): RedirectResponse
    {
        abort_unless($employee->business_id === $request->user()->business_id, 404);

        if ($employee->isOwnerOf($employee->business)) {
            return back()->withErrors(['employee' => 'The business owner\'s role cannot be changed here.']);
        }

        $validated = $request->validated();
        $roleIds = $validated['role_ids'];
        unset($validated['role_ids']);

        // `role_id` is the legacy single-role column, kept in step so
        // anything still reading it shows the first assigned role. The
        // pivot sync below is what actually changes permissions.
        $employee->update([...$validated, 'role_id' => $roleIds[0] ?? null]);
        $employee->roles()->sync($roleIds);

        return back()->with('status', 'employee-updated');
    }

    public function destroy(Request $request, User $employee): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('employees.delete'), 403);
        abort_unless($employee->business_id === $request->user()->business_id, 404);

        if ($employee->isOwnerOf($employee->business)) {
            return back()->withErrors(['employee' => 'The business owner cannot be removed.']);
        }

        $employee->delete();

        return back()->with('status', 'employee-removed');
    }
}
