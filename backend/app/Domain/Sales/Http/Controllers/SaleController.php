<?php

namespace App\Domain\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Sales\Exceptions\CreditSaleException;
use App\Domain\Sales\Http\Requests\SalePaymentStoreRequest;
use App\Domain\Sales\Http\Requests\SaleStoreRequest;
use App\Domain\Sales\Http\Requests\SaleVoidRequest;
use App\Domain\Sales\Http\Resources\SaleResource;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\SalePaymentService;
use App\Domain\Sales\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly SalePaymentService $salePaymentService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $businessId = $request->user()->business_id;

        $sales = Sale::query()
            ->where('business_id', $businessId)
            ->with(['customer', 'soldBy'])
            ->withCount('items')
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function ($q) use ($search) {
                    $q->where('sale_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sales/Orders/Index', [
            'sales' => SaleResource::collection($sales),
            'filters' => $request->only(['search', 'status', 'payment_status', 'source']),
        ]);
    }

    public function show(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        return Inertia::render('Sales/Orders/Show', [
            'sale' => new SaleResource($sale->load(['items', 'payments.receivedBy', 'customer', 'soldBy', 'branch', 'warehouse'])),
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

        return redirect()->route('sales.orders.show', $sale)->with('status', 'sale-completed');
    }

    public function void(SaleVoidRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->saleService->void($sale, $request->string('reason')->value(), $request->user()->id);
        } catch (CreditSaleException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'sale-voided');
    }

    public function recordPayment(SalePaymentStoreRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->salePaymentService->record($sale, [
                ...$request->validated(),
                'received_by' => $request->user()->id,
            ]);
        } catch (CreditSaleException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'payment-recorded');
    }
}
