<?php

namespace App\Domain\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Exceptions\PurchaseOrderException;
use App\Domain\Purchasing\Http\Requests\PurchaseOrderCancelRequest;
use App\Domain\Purchasing\Http\Requests\PurchaseOrderRejectRequest;
use App\Domain\Purchasing\Http\Requests\PurchaseOrderStoreRequest;
use App\Domain\Purchasing\Http\Requests\PurchaseOrderUpdateRequest;
use App\Domain\Purchasing\Http\Requests\SupplierPaymentStoreRequest;
use App\Domain\Purchasing\Http\Resources\PurchaseOrderResource;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Services\PurchaseOrderService;
use App\Domain\Purchasing\Services\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly SupplierPaymentService $supplierPaymentService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where('po_number', 'like', "%{$search}%");
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->string('supplier_id')))
            ->orderByDesc('order_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchasing/Orders/Index', [
            'orders' => PurchaseOrderResource::collection($orders),
            'suppliers' => Supplier::query()->where('status', Supplier::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'supplier_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('Purchasing/Orders/Form', $this->formOptions());
    }

    public function show(PurchaseOrder $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['items', 'supplier', 'branch', 'warehouse', 'approvedBy', 'goodsReceivedNotes.items.product:id,name', 'payments.paidBy']);

        return Inertia::render('Purchasing/Orders/Show', [
            'order' => new PurchaseOrderResource($order),
        ]);
    }

    public function edit(PurchaseOrder $order): Response
    {
        $this->authorize('update', $order);

        $order->load('items');

        return Inertia::render('Purchasing/Orders/Form', [
            ...$this->formOptions(),
            'order' => new PurchaseOrderResource($order),
        ]);
    }

    public function store(PurchaseOrderStoreRequest $request): RedirectResponse
    {
        try {
            $order = $this->purchaseOrderService->create([
                ...$request->validated(),
                'business_id' => $request->user()->business_id,
                'created_by' => $request->user()->id,
            ]);
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('purchasing.orders.show', $order)->with('status', 'purchase-order-created');
    }

    public function update(PurchaseOrderUpdateRequest $request, PurchaseOrder $order): RedirectResponse
    {
        try {
            $this->purchaseOrderService->update($order, $request->validated());
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('purchasing.orders.show', $order)->with('status', 'purchase-order-updated');
    }

    public function destroy(PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        try {
            $this->purchaseOrderService->delete($order);
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }

        return redirect()->route('purchasing.orders.index')->with('status', 'purchase-order-deleted');
    }

    public function duplicate(PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $duplicate = $this->purchaseOrderService->duplicate($order);

        return redirect()->route('purchasing.orders.edit', $duplicate)->with('status', 'purchase-order-duplicated');
    }

    public function submitForApproval(PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        try {
            $this->purchaseOrderService->submitForApproval($order);
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }

        return back()->with('status', 'purchase-order-submitted');
    }

    public function approve(PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('approve', $order);

        try {
            $this->purchaseOrderService->approve($order, request()->user()->id);
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }

        return back()->with('status', 'purchase-order-approved');
    }

    public function reject(PurchaseOrderRejectRequest $request, PurchaseOrder $order): RedirectResponse
    {
        try {
            $this->purchaseOrderService->reject($order, $request->user()->id, $request->validated('rejection_reason'));
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }

        return back()->with('status', 'purchase-order-rejected');
    }

    public function send(PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        try {
            $this->purchaseOrderService->send($order);
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }

        return back()->with('status', 'purchase-order-sent');
    }

    public function cancel(PurchaseOrderCancelRequest $request, PurchaseOrder $order): RedirectResponse
    {
        try {
            $this->purchaseOrderService->cancel($order, $request->validated('cancellation_reason'));
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }

        return back()->with('status', 'purchase-order-cancelled');
    }

    public function close(PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        try {
            $this->purchaseOrderService->close($order);
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }

        return back()->with('status', 'purchase-order-closed');
    }

    public function recordPayment(SupplierPaymentStoreRequest $request, PurchaseOrder $order): RedirectResponse
    {
        try {
            $this->supplierPaymentService->record($order, [
                ...$request->validated(),
                'paid_by' => $request->user()->id,
            ]);
        } catch (PurchaseOrderException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'payment-recorded');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $businessId = request()->user()->business_id;

        return [
            'suppliers' => Supplier::query()->where('business_id', $businessId)->where('status', Supplier::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('business_id', $businessId)->where('status', '!=', Product::STATUS_ARCHIVED)->orderBy('name')->get(['id', 'name', 'sku', 'cost_price']),
        ];
    }
}
