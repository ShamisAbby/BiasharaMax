<?php

namespace App\Domain\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Sales\Exceptions\SaleReturnException;
use App\Domain\Sales\Http\Requests\SaleReturnRejectRequest;
use App\Domain\Sales\Http\Requests\SaleReturnStoreRequest;
use App\Domain\Sales\Http\Resources\SaleReturnResource;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Sales\Services\SaleReturnDashboardService;
use App\Domain\Sales\Services\SaleReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleReturnController extends Controller
{
    public function __construct(
        private readonly SaleReturnService $saleReturnService,
    ) {}

    public function index(Request $request, SaleReturnDashboardService $dashboardService): Response
    {
        $this->authorize('viewAny', SaleReturn::class);

        $businessId = $request->user()->business_id;

        $returns = SaleReturn::query()
            ->with(['sale:id,sale_number', 'customer:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sales/Returns/Index', [
            'returns' => SaleReturnResource::collection($returns),
            'summary' => $dashboardService->summary($businessId),
            'reasonBreakdown' => $dashboardService->reasonBreakdown($businessId),
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(Sale $sale): Response
    {
        $this->authorize('create', SaleReturn::class);

        $sale->load('items', 'customer');

        return Inertia::render('Sales/Returns/Form', [
            'sale' => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'customer' => $sale->customer ? ['id' => $sale->customer->id, 'name' => $sale->customer->name] : null,
                'items' => $sale->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]),
            ],
        ]);
    }

    public function show(SaleReturn $return): Response
    {
        $this->authorize('view', $return);

        $return->load(['items.product:id,name', 'sale', 'customer', 'approvedBy']);

        return Inertia::render('Sales/Returns/Show', [
            'returnRecord' => new SaleReturnResource($return),
        ]);
    }

    public function store(SaleReturnStoreRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $return = $this->saleReturnService->create([
                ...$request->validated(),
                'business_id' => $request->user()->business_id,
                'sale_id' => $sale->id,
                'created_by' => $request->user()->id,
            ]);
        } catch (SaleReturnException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('sales.returns.show', $return)->with('status', 'return-requested');
    }

    public function approve(SaleReturn $return): RedirectResponse
    {
        $this->authorize('approve', $return);

        try {
            $this->saleReturnService->approve($return, request()->user()->id);
        } catch (SaleReturnException $e) {
            throw ValidationException::withMessages(['return' => $e->getMessage()]);
        }

        return back()->with('status', 'return-approved');
    }

    public function reject(SaleReturnRejectRequest $request, SaleReturn $return): RedirectResponse
    {
        try {
            $this->saleReturnService->reject($return, $request->user()->id, $request->validated('rejection_reason'));
        } catch (SaleReturnException $e) {
            throw ValidationException::withMessages(['return' => $e->getMessage()]);
        }

        return back()->with('status', 'return-rejected');
    }
}
