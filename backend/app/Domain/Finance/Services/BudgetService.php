<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Models\BudgetLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{account_id: string, period_start: string, period_end: string, budgeted_amount: string, notes?: string|null}>  $lines
     */
    public function create(string $businessId, array $data, array $lines): Budget
    {
        return DB::transaction(function () use ($businessId, $data, $lines) {
            $budget = Budget::create(array_merge($data, [
                'business_id' => $businessId,
                'status' => Budget::STATUS_DRAFT,
            ]));

            foreach ($lines as $line) {
                $budget->lines()->create($line);
            }

            return $budget;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Budget $budget, array $data, ?array $lines = null): Budget
    {
        return DB::transaction(function () use ($budget, $data, $lines) {
            $budget->update($data);

            if ($lines !== null) {
                $budget->lines()->delete();
                foreach ($lines as $line) {
                    $budget->lines()->create($line);
                }
            }

            return $budget->refresh();
        });
    }

    public function approve(Budget $budget, string $userId): Budget
    {
        if (! $budget->isDraft()) {
            throw new \RuntimeException("Budget '{$budget->name}' is not in draft status.");
        }

        $budget->update([
            'status' => Budget::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $budget->refresh();
    }

    public function activate(Budget $budget): Budget
    {
        if ($budget->status !== Budget::STATUS_APPROVED) {
            throw new \RuntimeException("Budget '{$budget->name}' must be approved before activation.");
        }

        // Deactivate any currently active budget for the same fiscal year
        Budget::query()
            ->where('business_id', $budget->business_id)
            ->where('fiscal_year', $budget->fiscal_year)
            ->where('status', Budget::STATUS_ACTIVE)
            ->where('id', '!=', $budget->id)
            ->update(['status' => Budget::STATUS_ARCHIVED]);

        $budget->update(['status' => Budget::STATUS_ACTIVE]);

        return $budget->refresh();
    }

    public function archive(Budget $budget): Budget
    {
        $budget->update(['status' => Budget::STATUS_ARCHIVED]);

        return $budget->refresh();
    }

    /**
     * Returns budget vs actual comparison for each line.
     *
     * @return array<int, array{account: Account, period_start: string, period_end: string, budgeted: string, actual: string, variance: string, variance_pct: string|null}>
     */
    public function budgetVsActual(Budget $budget): array
    {
        $lines = $budget->lines()->with('account')->get();

        return $lines->map(function (BudgetLine $line) {
            $actual = $this->ledger->accountActivity(
                $line->account,
                $line->period_start->toDateString(),
                $line->period_end->toDateString(),
            );

            $variance = bcsub($actual, (string) $line->budgeted_amount, 2);

            $variancePct = bccomp((string) $line->budgeted_amount, '0', 2) !== 0
                ? bcmul(bcdiv($variance, (string) $line->budgeted_amount, 6), '100', 2)
                : null;

            return [
                'account' => $line->account,
                'period_start' => $line->period_start->toDateString(),
                'period_end' => $line->period_end->toDateString(),
                'budgeted' => (string) $line->budgeted_amount,
                'actual' => $actual,
                'variance' => $variance,
                'variance_pct' => $variancePct,
            ];
        })->values()->all();
    }

    public function forBusiness(string $businessId): Collection
    {
        return Budget::query()
            ->where('business_id', $businessId)
            ->orderByDesc('fiscal_year')
            ->orderBy('name')
            ->get();
    }

    public function activeBudget(string $businessId, int $fiscalYear): ?Budget
    {
        return Budget::query()
            ->where('business_id', $businessId)
            ->where('fiscal_year', $fiscalYear)
            ->where('status', Budget::STATUS_ACTIVE)
            ->first();
    }
}
