<?php

namespace App\Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authentication\Models\User;
use App\Modules\Business\Http\Requests\EmployeeInviteRequest;
use App\Modules\Business\Http\Requests\EmployeeUpdateRequest;
use App\Modules\Business\Http\Resources\BranchResource;
use App\Modules\Business\Http\Resources\EmployeeResource;
use App\Modules\Business\Services\EmployeeInvitationService;
use App\Modules\RBAC\Http\Resources\RoleResource;
use App\Modules\RBAC\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeInvitationService $invitationService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('employees.view'), 403);

        $business = $request->user()->business;

        $employees = User::query()
            ->where('business_id', $business->getKey())
            ->with(['role', 'branch', 'business'])
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

        $employee->update($request->validated());

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
