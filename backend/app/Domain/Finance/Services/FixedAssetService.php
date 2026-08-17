<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\DepreciationSchedule;
use App\Domain\Finance\Models\FixedAsset;
use App\Domain\Finance\Models\JournalEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FixedAssetService
{
    public function __construct(
        private readonly ChartOfAccountsService $accounts,
        private readonly JournalPostingService $posting,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $businessId, array $data): FixedAsset
    {
        return DB::transaction(function () use ($businessId, $data) {
            // Default to system accounts if not specified
            $accumDepAccount = $this->accounts->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_ACCUMULATED_DEPRECIATION);
            $depExpAccount = $this->accounts->resolveSystemAccount($businessId, ChartOfAccountsService::KEY_DEPRECIATION_EXPENSE);

            $asset = FixedAsset::create(array_merge($data, [
                'business_id' => $businessId,
                'accumulated_depreciation_account_id' => $data['accumulated_depreciation_account_id'] ?? $accumDepAccount->id,
                'depreciation_expense_account_id' => $data['depreciation_expense_account_id'] ?? $depExpAccount->id,
                'status' => FixedAsset::STATUS_ACTIVE,
            ]));

            if ($asset->depreciation_method !== FixedAsset::METHOD_NONE) {
                $this->generateSchedule($asset);
            }

            return $asset;
        });
    }

    public function generateSchedule(FixedAsset $asset): void
    {
        // Clear existing pending schedules
        $asset->depreciationSchedule()->where('status', DepreciationSchedule::STATUS_PENDING)->delete();

        $cost = (string) $asset->acquisition_cost;
        $residual = (string) $asset->residual_value;
        $depreciableAmount = bcsub($cost, $residual, 2);

        if (bccomp($depreciableAmount, '0', 2) <= 0 || $asset->useful_life_months <= 0) {
            return;
        }

        $monthlyDepreciation = bcdiv($depreciableAmount, (string) $asset->useful_life_months, 6);
        $accumulated = '0.00';
        $bookValue = $cost;
        $start = $asset->acquisition_date->copy()->startOfMonth()->addMonth();

        for ($i = 0; $i < $asset->useful_life_months; $i++) {
            $periodDate = $start->copy()->addMonths($i);

            if ($asset->depreciation_method === FixedAsset::METHOD_DECLINING_BALANCE) {
                $monthlyDepreciation = bcmul($bookValue, bcdiv('2', (string) $asset->useful_life_months, 6), 6);
            }

            // Never depreciate below residual value
            $remainingDepreciable = bcsub($bookValue, $residual, 2);
            if (bccomp($remainingDepreciable, '0', 2) <= 0) {
                break;
            }

            $depAmount = bccomp($monthlyDepreciation, $remainingDepreciable, 6) > 0
                ? $remainingDepreciable
                : $monthlyDepreciation;

            $accumulated = bcadd($accumulated, $depAmount, 2);
            $bookValue = bcsub($bookValue, $depAmount, 2);

            DepreciationSchedule::create([
                'business_id' => $asset->business_id,
                'fixed_asset_id' => $asset->id,
                'period_date' => $periodDate->toDateString(),
                'depreciation_amount' => $depAmount,
                'accumulated_depreciation' => $accumulated,
                'book_value' => $bookValue,
                'status' => DepreciationSchedule::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Post depreciation for all pending schedule rows for a given year-month (YYYY-MM).
     */
    public function postMonthlyDepreciation(string $businessId, string $yearMonth, string $postedBy): int
    {
        $periodDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->toDateString();

        $schedules = DepreciationSchedule::query()
            ->where('business_id', $businessId)
            ->where('period_date', $periodDate)
            ->where('status', DepreciationSchedule::STATUS_PENDING)
            ->with('fixedAsset')
            ->get();

        $posted = 0;

        foreach ($schedules as $schedule) {
            $asset = $schedule->fixedAsset;

            if ($asset->status === FixedAsset::STATUS_DISPOSED) {
                continue;
            }

            $je = $this->posting->postImmediately($businessId, [
                'entry_date' => $periodDate,
                'type' => JournalEntry::TYPE_AUTO,
                'description' => "Depreciation — {$asset->asset_name} ({$yearMonth})",
            ], [
                [
                    'account_id' => $asset->depreciation_expense_account_id,
                    'debit' => (string) $schedule->depreciation_amount,
                    'credit' => '0',
                    'description' => "Depreciation: {$asset->asset_name}",
                ],
                [
                    'account_id' => $asset->accumulated_depreciation_account_id,
                    'debit' => '0',
                    'credit' => (string) $schedule->depreciation_amount,
                    'description' => "Accumulated depreciation: {$asset->asset_name}",
                ],
            ], $postedBy);

            $schedule->update([
                'status' => DepreciationSchedule::STATUS_POSTED,
                'journal_entry_id' => $je->id,
            ]);

            // Mark fully depreciated if no more pending rows
            if (bccomp((string) $schedule->book_value, (string) $asset->residual_value, 2) <= 0) {
                $asset->update(['status' => FixedAsset::STATUS_FULLY_DEPRECIATED]);
            }

            $posted++;
        }

        return $posted;
    }

    /**
     * Dispose of a fixed asset, posting a disposal JE and marking it disposed.
     */
    public function dispose(FixedAsset $asset, string $disposalDate, string $proceeds, string $cashAccountId, string $postedBy): JournalEntry
    {
        return DB::transaction(function () use ($asset, $disposalDate, $proceeds, $cashAccountId, $postedBy) {
            $cost = (string) $asset->acquisition_cost;
            $accumulatedDep = $asset->totalAccumulatedDepreciation();
            $bookValue = bcsub($cost, $accumulatedDep, 2);
            $gainLoss = bcsub($proceeds, $bookValue, 2);

            $gainAccount = $this->accounts->resolveSystemAccount($asset->business_id, ChartOfAccountsService::KEY_GAIN_ON_DISPOSAL);
            $lossAccount = $this->accounts->resolveSystemAccount($asset->business_id, ChartOfAccountsService::KEY_LOSS_ON_DISPOSAL);

            $lines = [
                // Remove accumulated depreciation (Dr: contra asset)
                [
                    'account_id' => $asset->accumulated_depreciation_account_id,
                    'debit' => $accumulatedDep,
                    'credit' => '0',
                    'description' => 'Remove accumulated depreciation',
                ],
                // Proceeds received (Dr: cash)
                [
                    'account_id' => $cashAccountId,
                    'debit' => $proceeds,
                    'credit' => '0',
                    'description' => 'Disposal proceeds',
                ],
                // Remove asset at cost (Cr: asset account)
                [
                    'account_id' => $asset->account_id,
                    'debit' => '0',
                    'credit' => $cost,
                    'description' => 'Remove asset at cost',
                ],
            ];

            if (bccomp($gainLoss, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $gainAccount->id,
                    'debit' => '0',
                    'credit' => $gainLoss,
                    'description' => 'Gain on disposal',
                ];
            } elseif (bccomp($gainLoss, '0', 2) < 0) {
                $lines[] = [
                    'account_id' => $lossAccount->id,
                    'debit' => ltrim($gainLoss, '-'),
                    'credit' => '0',
                    'description' => 'Loss on disposal',
                ];
            }

            $je = $this->posting->postImmediately($asset->business_id, [
                'entry_date' => $disposalDate,
                'type' => JournalEntry::TYPE_MANUAL,
                'description' => "Disposal of {$asset->asset_name}",
            ], $lines, $postedBy);

            // Cancel remaining pending depreciation rows
            $asset->depreciationSchedule()->where('status', DepreciationSchedule::STATUS_PENDING)->delete();

            $asset->update([
                'status' => FixedAsset::STATUS_DISPOSED,
                'disposal_date' => $disposalDate,
                'disposal_proceeds' => $proceeds,
                'disposal_journal_entry_id' => $je->id,
            ]);

            return $je;
        });
    }

    public function forBusiness(string $businessId): Collection
    {
        return FixedAsset::query()
            ->where('business_id', $businessId)
            ->with(['account', 'accumulatedDepreciationAccount'])
            ->orderBy('asset_code')
            ->get();
    }
}
