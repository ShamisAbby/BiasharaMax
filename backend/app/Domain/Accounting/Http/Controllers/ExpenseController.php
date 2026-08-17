<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Accounting\Http\Requests\ExpenseRejectRequest;
use App\Domain\Accounting\Http\Requests\ExpenseStoreRequest;
use App\Domain\Accounting\Http\Requests\ExpenseUpdateRequest;
use App\Domain\Accounting\Http\Resources\ExpenseCategoryResource;
use App\Domain\Accounting\Http\Resources\ExpenseResource;
use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Accounting\Services\ExpenseService;
use App\Domain\Business\Models\Branch;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = Expense::query()
            ->with(['category:id,name', 'supplier:id,name', 'employee:id,name', 'branch:id,name'])
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('expense_category_id'), fn ($query) => $query->where('expense_category_id', $request->string('expense_category_id')))
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Accounting/Expenses/Index', [
            'expenses' => ExpenseResource::collection($expenses),
            'categories' => ExpenseCategoryResource::collection(ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get()),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'expense_category_id']),
        ]);
    }

    public function store(ExpenseStoreRequest $request): RedirectResponse
    {
        $this->expenseService->create([
            ...$request->safe()->except('receipt'),
            'business_id' => $request->user()->business_id,
        ], $request->file('receipt'));

        return back()->with('status', 'expense-created');
    }

    public function update(ExpenseUpdateRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenseService->update($expense, $request->safe()->except('receipt'), $request->file('receipt'));

        return back()->with('status', 'expense-updated');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);
        $this->expenseService->delete($expense);

        return back()->with('status', 'expense-deleted');
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);
        $this->expenseService->approve($expense, request()->user()->id);

        return back()->with('status', 'expense-approved');
    }

    public function reject(ExpenseRejectRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenseService->reject($expense, $request->user()->id, $request->validated('rejection_reason'));

        return back()->with('status', 'expense-rejected');
    }

    public function markPaid(Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);
        $this->expenseService->markPaid($expense);

        return back()->with('status', 'expense-marked-paid');
    }
}
