<?php

namespace App\Domain\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Http\Requests\WarehouseStoreRequest;
use App\Domain\Business\Http\Requests\WarehouseUpdateRequest;
use App\Domain\Business\Http\Resources\BranchResource;
use App\Domain\Business\Http\Resources\WarehouseResource;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Subscription\Exceptions\PlanLimitExceededException;
use App\Domain\Subscription\Services\SubscriptionLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly SubscriptionLimitService $limits,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Warehouse::class);

        $business = $request->user()->business;

        return Inertia::render('Settings/Warehouses', [
            'warehouses' => WarehouseResource::collection(
                $business->warehouses()->with('branch')->orderBy('name')->get()
            ),
            'branches' => BranchResource::collection(
                $business->branches()->where('status', 'active')->orderBy('name')->get()
            ),
        ]);
    }

    public function store(WarehouseStoreRequest $request): RedirectResponse
    {
        try {
            $this->limits->ensureCanAdd($request->user()->business, 'warehouses');
        } catch (PlanLimitExceededException $e) {
            return back()->withErrors(['warehouse' => $e->getMessage()]);
        }

        Warehouse::query()->create([
            'business_id' => $request->user()->business_id,
            ...$request->validated(),
        ]);

        return back()->with('status', 'warehouse-created');
    }

    public function update(WarehouseUpdateRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return back()->with('status', 'warehouse-updated');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        $warehouse->delete();

        return back()->with('status', 'warehouse-deleted');
    }
}
