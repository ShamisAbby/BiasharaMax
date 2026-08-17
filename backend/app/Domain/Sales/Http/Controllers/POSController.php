<?php

namespace App\Domain\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Exceptions\CreditSaleException;
use App\Domain\Sales\Http\Requests\SaleStoreRequest;
use App\Domain\Sales\Http\Resources\SaleResource;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class POSController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('pos.view'), 403);

        $businessId = $request->user()->business_id;

        $products = Product::query()
            ->where('business_id', $businessId)
            ->where('status', Product::STATUS_ACTIVE)
            ->with(['inventories'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'tax_rate'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'selling_price' => $product->selling_price,
                'tax_rate' => $product->tax_rate,
                'stock_on_hand' => (float) $product->inventories->sum('quantity'),
            ]);

        $branches = Branch::query()->where('business_id', $businessId)->where('status', 'active')->get(['id', 'name', 'is_main']);
        $warehouses = Warehouse::query()->where('business_id', $businessId)->where('status', 'active')->get(['id', 'name', 'branch_id', 'is_default']);
        $customers = Customer::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone', 'customer_type', 'credit_limit', 'current_balance']);

        $defaultBranchId = $request->user()->branch_id ?? $branches->firstWhere('is_main', true)?->id ?? $branches->first()?->id;
        $defaultWarehouse = $warehouses->firstWhere('branch_id', $defaultBranchId) ?? $warehouses->first();

        return Inertia::render('Sales/POS/Terminal', [
            'products' => $products,
            'branches' => $branches,
            'warehouses' => $warehouses,
            'customers' => $customers,
            'defaultBranchId' => $defaultBranchId,
            'defaultWarehouseId' => $defaultWarehouse?->id,
        ]);
    }

    public function store(SaleStoreRequest $request): RedirectResponse
    {
        try {
            $sale = $this->saleService->create([
                ...$request->validated(),
                'business_id' => $request->user()->business_id,
                'sold_by' => $request->user()->id,
            ]);
        } catch (CreditSaleException|InsufficientStockException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('pos.receipt', $sale)->with('status', 'sale-completed');
    }

    public function receipt(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        return Inertia::render('Sales/POS/Receipt', [
            'sale' => new SaleResource($sale->load(['items', 'payments', 'customer', 'soldBy', 'branch'])),
        ]);
    }
}
