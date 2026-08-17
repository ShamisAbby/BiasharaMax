<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Authentication\Models\User;
use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Models\SalaryAllowance;
use App\Domain\Payroll\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeProfileController extends Controller
{
    public function __construct(
        private readonly PayrollService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EmployeeProfile::class);

        $employees = $this->service->employeesForBusiness($request->user()->business_id);

        $enrolledUserIds = EmployeeProfile::query()
            ->where('business_id', $request->user()->business_id)
            ->pluck('user_id');

        $businessUsers = User::query()
            ->where('business_id', $request->user()->business_id)
            ->whereNotIn('id', $enrolledUserIds)
            ->get(['id', 'name', 'email']);

        return Inertia::render('Payroll/Employees/Index', [
            'employees' => $employees->map(fn (EmployeeProfile $e) => [
                'id' => $e->id,
                'employee_number' => $e->employee_number,
                'name' => $e->user->name,
                'email' => $e->user->email,
                'position' => $e->position,
                'department' => $e->department,
                'base_salary' => $e->base_salary,
                'status' => $e->status,
                'employment_type' => $e->employment_type,
                'gross_salary' => $e->grossSalary(),
            ]),
            'businessUsers' => $businessUsers->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', EmployeeProfile::class);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'employee_number' => ['required', 'string', 'max:20'],
            'employment_date' => ['required', 'date'],
            'employment_type' => ['required', 'in:full_time,part_time,contract'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'salary_cycle' => ['required', 'in:monthly,bi_weekly,weekly'],
            'bank_account_number' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string'],
            'allowances' => ['nullable', 'array'],
            'allowances.*.allowance_type' => ['required', 'string'],
            'allowances.*.amount' => ['required', 'numeric', 'min:0'],
            'allowances.*.is_taxable' => ['boolean'],
        ]);

        $this->service->createEmployeeProfile(
            $request->user()->business_id,
            $data['user_id'],
            array_merge($data, ['created_by' => $request->user()->id]),
            $data['allowances'] ?? [],
        );

        return back()->with('status', 'employee-created');
    }

    public function show(Request $request, EmployeeProfile $profile): Response
    {
        $this->authorize('view', $profile);

        $profile->load(['user:id,name,email', 'allowances', 'payslips.period']);

        return Inertia::render('Payroll/Employees/Show', [
            'employee' => [
                'id' => $profile->id,
                'employee_number' => $profile->employee_number,
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'employment_date' => $profile->employment_date->toDateString(),
                'employment_type' => $profile->employment_type,
                'department' => $profile->department,
                'position' => $profile->position,
                'base_salary' => $profile->base_salary,
                'salary_cycle' => $profile->salary_cycle,
                'status' => $profile->status,
                'gross_salary' => $profile->grossSalary(),
                'allowances' => $profile->allowances->map(fn ($a) => [
                    'id' => $a->id,
                    'allowance_type' => $a->allowance_type,
                    'amount' => $a->amount,
                    'is_taxable' => $a->is_taxable,
                    'is_active' => $a->is_active,
                ]),
                'payslips' => $profile->payslips->take(12)->map(fn ($p) => [
                    'id' => $p->id,
                    'period_name' => $p->period->period_name,
                    'gross_salary' => $p->gross_salary,
                    'net_salary' => $p->net_salary,
                    'status' => $p->status,
                    'paid_at' => $p->paid_at?->toDateString(),
                ]),
            ],
        ]);
    }

    public function update(Request $request, EmployeeProfile $profile): RedirectResponse
    {
        $this->authorize('manage', EmployeeProfile::class);

        $data = $request->validate([
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,on_leave,terminated'],
            'termination_date' => ['nullable', 'date'],
        ]);

        $profile->update(array_merge($data, ['updated_by' => $request->user()->id]));

        return back()->with('status', 'employee-updated');
    }
}
