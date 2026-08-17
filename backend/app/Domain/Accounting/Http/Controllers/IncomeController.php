<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Accounting\Http\Requests\IncomeStoreRequest;
use App\Domain\Accounting\Http\Requests\IncomeUpdateRequest;
use App\Domain\Accounting\Http\Resources\IncomeResource;
use App\Domain\Accounting\Models\Income;
use App\Domain\Accounting\Services\IncomeService;
use App\Domain\Business\Models\Branch;
use App\Domain\Sales\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    public function __construct(
        private readonly IncomeService $incomeService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Income::class);

        $incomes = Income::query()
            ->with(['customer:id,name', 'branch:id,name'])
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderByDesc('income_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Accounting/Income/Index', [
            'incomes' => IncomeResource::collection($incomes),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function store(IncomeStoreRequest $request): RedirectResponse
    {
        $this->incomeService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return back()->with('status', 'income-created');
    }

    public function update(IncomeUpdateRequest $request, Income $income): RedirectResponse
    {
        $this->incomeService->update($income, $request->validated());

        return back()->with('status', 'income-updated');
    }

    public function destroy(Income $income): RedirectResponse
    {
        $this->authorize('delete', $income);
        $this->incomeService->delete($income);

        return back()->with('status', 'income-deleted');
    }
}
