<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Models\Account;
use App\Domain\Payroll\Models\PayrollPeriod;
use App\Domain\Payroll\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPeriodController extends Controller
{
    public function __construct(
        private readonly PayrollService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $periods = $this->service->periodsForBusiness($request->user()->business_id);

        return Inertia::render('Payroll/Periods/Index', [
            'periods' => $periods->map(fn (PayrollPeriod $p) => [
                'id' => $p->id,
                'period_name' => $p->period_name,
                'period_start' => $p->period_start->toDateString(),
                'period_end' => $p->period_end->toDateString(),
                'status' => $p->status,
                'total_gross' => $p->total_gross,
                'total_deductions' => $p->total_deductions,
                'total_net' => $p->total_net,
                'paid_at' => $p->paid_at?->toDateString(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);

        $data = $request->validate([
            'period_name' => ['required', 'string', 'max:100'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'salary_cycle' => ['required', 'in:monthly,bi_weekly,weekly'],
        ]);

        $this->service->createPeriod($request->user()->business_id, array_merge($data, ['created_by' => $request->user()->id]));

        return back()->with('status', 'payroll-period-created');
    }

    public function show(Request $request, PayrollPeriod $period): Response
    {
        $this->authorize('view', $period);

        $period->load(['payslips.employeeProfile.user', 'journalEntry:id,entry_number,status']);

        $cashAccounts = Account::query()
            ->where('business_id', $request->user()->business_id)
            ->whereIn('type', [Account::TYPE_ASSET])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Payroll/Periods/Show', [
            'period' => [
                'id' => $period->id,
                'period_name' => $period->period_name,
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
                'status' => $period->status,
                'total_gross' => $period->total_gross,
                'total_deductions' => $period->total_deductions,
                'total_net' => $period->total_net,
                'approved_at' => $period->approved_at?->toDateString(),
                'paid_at' => $period->paid_at?->toDateString(),
                'journal_entry' => $period->journalEntry ? ['id' => $period->journalEntry->id, 'entry_number' => $period->journalEntry->entry_number] : null,
                'payslips' => $period->payslips->map(fn ($p) => [
                    'id' => $p->id,
                    'employee_name' => $p->employeeProfile->user->name,
                    'employee_number' => $p->employeeProfile->employee_number,
                    'basic_salary' => $p->basic_salary,
                    'total_allowances' => $p->total_allowances,
                    'gross_salary' => $p->gross_salary,
                    'total_deductions' => $p->total_deductions,
                    'net_salary' => $p->net_salary,
                    'status' => $p->status,
                ]),
            ],
            'cashAccounts' => $cashAccounts->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
            ]),
        ]);
    }

    public function generate(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);

        $count = $this->service->generatePayslips($period);

        return back()->with('status', "payslips-generated:{$count}");
    }

    public function approve(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->authorize('approve', $period);

        try {
            $this->service->approvePeriod($period, $request->user()->id);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['period' => $e->getMessage()]);
        }

        return back()->with('status', 'payroll-approved');
    }

    public function pay(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->authorize('pay', $period);

        $data = $request->validate([
            'cash_account_id' => ['required', 'uuid', 'exists:accounts,id'],
        ]);

        try {
            $this->service->processPayment($period, $request->user()->id, $data['cash_account_id']);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['period' => $e->getMessage()]);
        }

        return back()->with('status', 'payroll-paid');
    }
}
