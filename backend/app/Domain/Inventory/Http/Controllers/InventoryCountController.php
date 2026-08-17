<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Http\Resources\WarehouseResource;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Http\Requests\InventoryCountRecordRequest;
use App\Domain\Inventory\Http\Requests\InventoryCountStoreRequest;
use App\Domain\Inventory\Http\Resources\InventoryCountResource;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\InventoryCountItem;
use App\Domain\Inventory\Services\InventoryCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCountController extends Controller
{
    public function __construct(
        private readonly InventoryCountService $inventoryCountService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryCount::class);

        $businessId = $request->user()->business_id;

        $counts = InventoryCount::query()
            ->where('business_id', $businessId)
            ->with(['warehouse', 'items.product'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Inventory/InventoryCounts', [
            'counts' => InventoryCountResource::collection($counts),
            'warehouses' => WarehouseResource::collection(Warehouse::query()->where('business_id', $businessId)->orderBy('name')->get()),
        ]);
    }

    public function store(InventoryCountStoreRequest $request): RedirectResponse
    {
        $count = $this->inventoryCountService->start($request->user()->business_id, $request->validated('warehouse_id'), $request->user());

        return back()->with('status', 'inventory-count-started')->with('inventory_count_id', $count->id);
    }

    public function recordItem(InventoryCountRecordRequest $request, InventoryCountItem $item): RedirectResponse
    {
        $this->inventoryCountService->recordCount($item, $request->validated('counted_quantity'));

        return back()->with('status', 'inventory-count-item-recorded');
    }

    public function complete(InventoryCount $count): RedirectResponse
    {
        $this->authorize('complete', $count);

        $this->inventoryCountService->complete($count->load('items'), request()->user());

        return back()->with('status', 'inventory-count-completed');
    }
}
