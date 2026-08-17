<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Http\Resources\WarehouseResource;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Http\Requests\StockTransferStoreRequest;
use App\Domain\Inventory\Http\Resources\StockTransferResource;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferService $stockTransferService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockTransfer::class);

        $businessId = $request->user()->business_id;

        $transfers = StockTransfer::query()
            ->where('business_id', $businessId)
            ->with(['fromWarehouse', 'toWarehouse', 'items.product'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Inventory/StockTransfers', [
            'transfers' => StockTransferResource::collection($transfers),
            'warehouses' => WarehouseResource::collection(Warehouse::query()->where('business_id', $businessId)->orderBy('name')->get()),
            'products' => Product::query()->where('business_id', $businessId)->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    public function store(StockTransferStoreRequest $request): RedirectResponse
    {
        $this->stockTransferService->create($request->user()->business_id, $request->user(), $request->validated());

        return back()->with('status', 'stock-transfer-created');
    }

    public function dispatch(StockTransfer $transfer): RedirectResponse
    {
        $this->authorize('dispatch', $transfer);

        try {
            $this->stockTransferService->dispatch($transfer, request()->user());
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', 'stock-transfer-dispatched');
    }

    public function receive(StockTransfer $transfer): RedirectResponse
    {
        $this->authorize('receive', $transfer);

        $this->stockTransferService->receive($transfer, request()->user());

        return back()->with('status', 'stock-transfer-received');
    }

    public function cancel(StockTransfer $transfer): RedirectResponse
    {
        $this->authorize('cancel', $transfer);

        try {
            $this->stockTransferService->cancel($transfer);
        } catch (\LogicException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', 'stock-transfer-cancelled');
    }
}
