<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Finance\Services\ChartOfAccountsService;
use Illuminate\Support\Str;

class ExpenseCategoryService
{
    public function __construct(
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ExpenseCategory
    {
        $data['slug'] = $this->uniqueSlug($data['business_id'], $data['name']);

        $category = ExpenseCategory::create($data);

        // Keeps every expense category posting to its own ledger account
        // without requiring a manual Chart of Accounts step.
        $this->chartOfAccountsService->seedExpenseCategoryAccounts($category->business_id);

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($category->business_id, $data['name'], $category->id);
        }

        $category->update($data);

        return $category->refresh();
    }

    public function delete(ExpenseCategory $category): void
    {
        $category->delete();
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            ExpenseCategory::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->withTrashed()
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
