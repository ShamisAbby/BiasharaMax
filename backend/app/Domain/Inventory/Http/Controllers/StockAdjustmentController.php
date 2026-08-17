<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Http\Resources\BranchResource;
use App\Domain\Business\Http\Resources\WarehouseResource;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Http\Requests\StockAdjustmentStoreRequest;
use App\Domain\Inventory\Http\Resources\StockAdjustmentResource;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Services\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockAdjustmentService $stockAdjustmentService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $businessId = $request->user()->business_id;

        $adjustments = StockAdjustment::query()
            ->where('business_id', $businessId)
            ->with(['warehouse', 'items.product'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Inventory/StockAdjustments', [
            'adjustments' => StockAdjustmentResource::collection($adjustments),
            'branches' => BranchResource::collection(Branch::query()->where('business_id', $businessId)->orderBy('name')->get()),
            'warehouses' => WarehouseResource::collection(Warehouse::query()->where('business_id', $businessId)->orderBy('name')->get()),
            'products' => Product::query()->where('business_id', $businessId)->orderBy('name')->get(['id', 'name', 'sku', 'has_expiry', 'has_batch']),
        ]);
    }

    public function store(StockAdjustmentStoreRequest $request): RedirectResponse
    {
        $this->stockAdjustmentService->create($request->user()->business_id, $request->user(), $request->validated());

        return back()->with('status', 'stock-adjustment-created');
    }

    public function complete(StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('complete', $adjustment);

        try {
            $this->stockAdjustmentService->complete($adjustment, request()->user());
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['adjustment' => $e->getMessage()]);
        }

        return back()->with('status', 'stock-adjustment-completed');
    }

    public function destroy(StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('delete', $adjustment);

        $adjustment->delete();

        return back()->with('status', 'stock-adjustment-deleted');
    }
}
