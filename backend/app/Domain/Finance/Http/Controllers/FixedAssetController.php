<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\DepreciationSchedule;
use App\Domain\Finance\Models\FixedAsset;
use App\Domain\Finance\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    public function __construct(
        private readonly FixedAssetService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FixedAsset::class);

        $businessId = $request->user()->business_id;
        $assets = $this->service->forBusiness($businessId);

        $assetAccounts = Account::query()
            ->where('business_id', $businessId)
            ->where('type', Account::TYPE_ASSET)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Finance/Assets/Index', [
            'assets' => $assets->map(fn (FixedAsset $a) => [
                'id' => $a->id,
                'asset_code' => $a->asset_code,
                'asset_name' => $a->asset_name,
                'category' => $a->category,
                'acquisition_date' => $a->acquisition_date->toDateString(),
                'acquisition_cost' => $a->acquisition_cost,
                'depreciation_method' => $a->depreciation_method,
                'status' => $a->status,
                'book_value' => $a->currentBookValue(),
                'accumulated_depreciation' => $a->totalAccumulatedDepreciation(),
            ]),
            'assetAccounts' => $assetAccounts->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', FixedAsset::class);

        $data = $request->validate([
            'asset_code' => ['required', 'string', 'max:50'],
            'asset_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:land,building,vehicle,equipment,furniture,intangible,other'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'depreciation_method' => ['required', 'in:straight_line,declining_balance,none'],
        ]);

        $this->service->create($request->user()->business_id, array_merge($data, [
            'created_by' => $request->user()->id,
        ]));

        return back()->with('status', 'asset-created');
    }

    public function show(Request $request, FixedAsset $asset): Response
    {
        $this->authorize('view', $asset);

        $asset->load(['account', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount']);

        $schedule = $asset->depreciationSchedule()
            ->with('journalEntry:id,entry_number,status')
            ->orderBy('period_date')
            ->get();

        $cashAccounts = Account::query()
            ->where('business_id', $request->user()->business_id)
            ->whereIn('code', ['1000', '1010'])
            ->orWhere(fn ($q) => $q->where('business_id', $request->user()->business_id)->whereIn('type', [Account::TYPE_ASSET])->where('is_active', true))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Finance/Assets/Show', [
            'asset' => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'asset_name' => $asset->asset_name,
                'category' => $asset->category,
                'acquisition_date' => $asset->acquisition_date->toDateString(),
                'acquisition_cost' => $asset->acquisition_cost,
                'useful_life_months' => $asset->useful_life_months,
                'residual_value' => $asset->residual_value,
                'depreciation_method' => $asset->depreciation_method,
                'status' => $asset->status,
                'book_value' => $asset->currentBookValue(),
                'accumulated_depreciation' => $asset->totalAccumulatedDepreciation(),
                'account' => ['code' => $asset->account->code, 'name' => $asset->account->name],
            ],
            'schedule' => $schedule->map(fn (DepreciationSchedule $s) => [
                'id' => $s->id,
                'period_date' => $s->period_date->toDateString(),
                'depreciation_amount' => $s->depreciation_amount,
                'accumulated_depreciation' => $s->accumulated_depreciation,
                'book_value' => $s->book_value,
                'status' => $s->status,
                'entry_number' => $s->journalEntry?->entry_number,
            ]),
            'cashAccounts' => $cashAccounts->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
            ]),
        ]);
    }

    public function destroy(FixedAsset $asset): RedirectResponse
    {
        $this->authorize('manage', FixedAsset::class);

        if ($asset->status !== FixedAsset::STATUS_ACTIVE && $asset->status !== FixedAsset::STATUS_FULLY_DEPRECIATED) {
            throw ValidationException::withMessages(['asset' => 'Cannot delete a disposed asset.']);
        }

        $asset->delete();

        return redirect()->route('finance.assets.index')->with('status', 'asset-deleted');
    }

    public function dispose(Request $request, FixedAsset $asset): RedirectResponse
    {
        $this->authorize('dispose', $asset);

        $data = $request->validate([
            'disposal_date' => ['required', 'date'],
            'proceeds' => ['required', 'numeric', 'min:0'],
            'cash_account_id' => ['required', 'uuid', 'exists:accounts,id'],
        ]);

        try {
            $this->service->dispose(
                $asset,
                $data['disposal_date'],
                (string) $data['proceeds'],
                $data['cash_account_id'],
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['asset' => $e->getMessage()]);
        }

        return back()->with('status', 'asset-disposed');
    }

    public function postDepreciation(Request $request): RedirectResponse
    {
        $this->authorize('depreciate', FixedAsset::class);

        $data = $request->validate([
            'year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $posted = $this->service->postMonthlyDepreciation(
            $request->user()->business_id,
            $data['year_month'],
            $request->user()->id,
        );

        return back()->with('status', "depreciation-posted:{$posted}");
    }
}
