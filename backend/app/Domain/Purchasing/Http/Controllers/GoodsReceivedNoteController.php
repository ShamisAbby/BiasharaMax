<?php

namespace App\Domain\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Purchasing\Exceptions\GoodsReceivedException;
use App\Domain\Purchasing\Http\Requests\GoodsReceivedNoteStoreRequest;
use App\Domain\Purchasing\Http\Resources\GoodsReceivedNoteResource;
use App\Domain\Purchasing\Http\Resources\PurchaseOrderResource;
use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Services\GoodsReceivedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceivedNoteController extends Controller
{
    public function __construct(
        private readonly GoodsReceivedService $goodsReceivedService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', GoodsReceivedNote::class);

        $notes = GoodsReceivedNote::query()
            ->with('purchaseOrder.supplier:id,name')
            ->latest('received_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchasing/GoodsReceived/Index', [
            'notes' => GoodsReceivedNoteResource::collection($notes),
        ]);
    }

    public function create(PurchaseOrder $order): Response
    {
        $this->authorize('create', GoodsReceivedNote::class);
        $this->authorize('view', $order);

        $order->load(['items', 'supplier', 'branch', 'warehouse']);

        return Inertia::render('Purchasing/GoodsReceived/Form', [
            'order' => new PurchaseOrderResource($order),
            'branches' => Branch::query()->where('business_id', $order->business_id)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->where('business_id', $order->business_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(GoodsReceivedNote $note): Response
    {
        $this->authorize('view', $note);

        $note->load(['items.product:id,name', 'purchaseOrder.supplier', 'warehouse', 'receivedBy']);

        return Inertia::render('Purchasing/GoodsReceived/Show', [
            'note' => new GoodsReceivedNoteResource($note),
        ]);
    }

    public function store(GoodsReceivedNoteStoreRequest $request, PurchaseOrder $order): RedirectResponse
    {
        try {
            $note = $this->goodsReceivedService->create([
                ...$request->validated(),
                'business_id' => $request->user()->business_id,
                'purchase_order_id' => $order->id,
                'received_by' => $request->user()->id,
            ]);
        } catch (GoodsReceivedException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('purchasing.goods-received.show', $note)->with('status', 'goods-received-recorded');
    }
}
