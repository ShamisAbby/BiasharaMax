<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Accounting\Http\Requests\ExpenseCategoryStoreRequest;
use App\Domain\Accounting\Http\Requests\ExpenseCategoryUpdateRequest;
use App\Domain\Accounting\Http\Resources\ExpenseCategoryResource;
use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Accounting\Services\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private readonly ExpenseCategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        return Inertia::render('Accounting/ExpenseCategories/Index', [
            'categories' => ExpenseCategoryResource::collection($categories),
        ]);
    }

    public function store(ExpenseCategoryStoreRequest $request): RedirectResponse
    {
        $this->categoryService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return back()->with('status', 'expense-category-created');
    }

    public function update(ExpenseCategoryUpdateRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $this->categoryService->update($category, $request->validated());

        return back()->with('status', 'expense-category-updated');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        $this->categoryService->delete($category);

        return back()->with('status', 'expense-category-deleted');
    }
}
