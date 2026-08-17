<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Services\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Budget::class);

        $businessId = $request->user()->business_id;
        $budgets = $this->service->forBusiness($businessId);

        $glAccounts = Account::query()
            ->where('business_id', $businessId)
            ->whereIn('type', [Account::TYPE_INCOME, Account::TYPE_EXPENSE])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Finance/Budgets/Index', [
            'budgets' => $budgets->map(fn (Budget $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'fiscal_year' => $b->fiscal_year,
                'status' => $b->status,
                'description' => $b->description,
                'approved_at' => $b->approved_at?->toDateString(),
                'lines_count' => $b->lines()->count(),
            ]),
            'glAccounts' => $glAccounts->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', Budget::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'lines.*.period_start' => ['required', 'date'],
            'lines.*.period_end' => ['required', 'date', 'after_or_equal:lines.*.period_start'],
            'lines.*.budgeted_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);

        $this->service->create(
            $request->user()->business_id,
            [
                'name' => $data['name'],
                'fiscal_year' => $data['fiscal_year'],
                'description' => $data['description'] ?? null,
                'created_by' => $request->user()->id,
            ],
            $data['lines'],
        );

        return back()->with('status', 'budget-created');
    }

    public function show(Request $request, Budget $budget): Response
    {
        $this->authorize('view', $budget);

        $vsActual = $this->service->budgetVsActual($budget);

        $budget->load('lines.account');

        return Inertia::render('Finance/Budgets/Show', [
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'fiscal_year' => $budget->fiscal_year,
                'description' => $budget->description,
                'status' => $budget->status,
                'approved_at' => $budget->approved_at?->toDateString(),
                'lines' => $budget->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'account' => ['id' => $line->account->id, 'code' => $line->account->code, 'name' => $line->account->name],
                    'period_start' => $line->period_start->toDateString(),
                    'period_end' => $line->period_end->toDateString(),
                    'budgeted_amount' => $line->budgeted_amount,
                    'notes' => $line->notes,
                ]),
            ],
            'vsActual' => array_map(fn ($row) => [
                'account_code' => $row['account']->code,
                'account_name' => $row['account']->name,
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end'],
                'budgeted' => $row['budgeted'],
                'actual' => $row['actual'],
                'variance' => $row['variance'],
                'variance_pct' => $row['variance_pct'],
            ], $vsActual),
        ]);
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('manage', Budget::class);

        if ($budget->isActive()) {
            throw ValidationException::withMessages(['budget' => 'An active budget cannot be deleted.']);
        }

        $budget->delete();

        return redirect()->route('finance.budgets.index')->with('status', 'budget-deleted');
    }

    public function approve(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('approve', $budget);

        try {
            $this->service->approve($budget, $request->user()->id);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['budget' => $e->getMessage()]);
        }

        return back()->with('status', 'budget-approved');
    }

    public function activate(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('activate', $budget);

        try {
            $this->service->activate($budget);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['budget' => $e->getMessage()]);
        }

        return back()->with('status', 'budget-activated');
    }
}
