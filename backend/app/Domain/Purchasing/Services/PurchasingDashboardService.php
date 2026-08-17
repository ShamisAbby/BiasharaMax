<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Support\Carbon;

/**
 * Every figure here is computed live from real purchase_orders,
 * goods_received_notes and (for spend) accounting expenses rows — no
 * AI scoring, no fabricated supplier ratings. Average lead time is the
 * one "performance" metric included, since it's directly computable
 * from real sent_at/received_at timestamps rather than guessed.
 */
class PurchasingDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        return [
            'total_purchase_orders_count' => PurchaseOrder::query()
                ->where('business_id', $businessId)
                ->count(),
            'pending_purchase_orders_count' => PurchaseOrder::query()
                ->where('business_id', $businessId)
                ->whereIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING_APPROVAL])
                ->count(),
            'completed_orders_count' => PurchaseOrder::query()
                ->where('business_id', $businessId)
                ->whereIn('status', [PurchaseOrder::STATUS_FULLY_RECEIVED, PurchaseOrder::STATUS_CLOSED])
                ->count(),
            'pending_deliveries_count' => PurchaseOrder::query()
                ->where('business_id', $businessId)
                ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->count(),
            'today_receipts_count' => GoodsReceivedNote::query()
                ->where('business_id', $businessId)
                ->whereDate('received_at', Carbon::today())
                ->count(),
            'total_order_value' => (float) PurchaseOrder::query()
                ->where('business_id', $businessId)
                ->sum('total_amount'),
            'purchase_value_this_month' => (float) Expense::query()
                ->where('business_id', $businessId)
                ->whereNotNull('supplier_id')
                ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount'),
            'active_suppliers_count' => Supplier::query()
                ->where('business_id', $businessId)
                ->where('status', Supplier::STATUS_ACTIVE)
                ->count(),
        ];
    }

    /**
     * Day-by-day counts/value of purchase orders *created* over the last 7
     * days — used to draw the small stat-card trend lines from real rows
     * rather than fabricated series.
     *
     * @return array{orders: array<int, int>, completed: array<int, int>, value: array<int, float>}
     */
    public function trend(string $businessId, int $days = 7): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $orders = PurchaseOrder::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $start)
            ->get(['status', 'total_amount', 'created_at']);

        $ordersByDay = [];
        $completedByDay = [];
        $valueByDay = [];

        for ($i = 0; $i < $days; $i++) {
            $key = $start->copy()->addDays($i)->toDateString();
            $ordersByDay[$key] = 0;
            $completedByDay[$key] = 0;
            $valueByDay[$key] = 0.0;
        }

        foreach ($orders as $order) {
            $key = $order->created_at->toDateString();

            if (! array_key_exists($key, $ordersByDay)) {
                continue;
            }

            $ordersByDay[$key]++;
            $valueByDay[$key] += (float) $order->total_amount;

            if (in_array($order->status, [PurchaseOrder::STATUS_FULLY_RECEIVED, PurchaseOrder::STATUS_CLOSED], true)) {
                $completedByDay[$key]++;
            }
        }

        return [
            'orders' => array_values($ordersByDay),
            'completed' => array_values($completedByDay),
            'value' => array_values($valueByDay),
        ];
    }

    /**
     * @return array<int, array{supplier_id: string, name: string, total_spend: float}>
     */
    public function topSuppliers(string $businessId, int $limit = 5): array
    {
        return Expense::query()
            ->where('business_id', $businessId)
            ->whereNotNull('supplier_id')
            ->with('supplier:id,name')
            ->selectRaw('supplier_id, sum(amount) as total')
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->filter(fn ($row) => $row->supplier !== null)
            ->map(fn ($row) => [
                'supplier_id' => $row->supplier_id,
                'name' => $row->supplier->name,
                'total_spend' => (float) $row->total,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, po_number: string, supplier_name: string, status: string, total_amount: float, order_date: string}>
     */
    public function recentPurchaseOrders(string $businessId, int $limit = 5): array
    {
        return PurchaseOrder::query()
            ->where('business_id', $businessId)
            ->with('supplier:id,name')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier_name' => $po->supplier?->name ?? 'Unknown',
                'status' => $po->status,
                'total_amount' => (float) $po->total_amount,
                'order_date' => $po->order_date->toDateString(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, grn_number: string, po_number: string, supplier_name: string, received_at: string}>
     */
    public function recentDeliveries(string $businessId, int $limit = 5): array
    {
        return GoodsReceivedNote::query()
            ->where('business_id', $businessId)
            ->with('purchaseOrder.supplier:id,name')
            ->latest('received_at')
            ->limit($limit)
            ->get()
            ->map(fn (GoodsReceivedNote $grn) => [
                'id' => $grn->id,
                'grn_number' => $grn->grn_number,
                'po_number' => $grn->purchaseOrder->po_number,
                'supplier_name' => $grn->purchaseOrder->supplier?->name ?? 'Unknown',
                'received_at' => $grn->received_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Average real lead time (days between sending the PO and the first
     * delivery against it) per supplier — only counted for orders that
     * actually have both a sent_at and at least one delivery.
     *
     * @return array<int, array{supplier_id: string, name: string, average_lead_time_days: float, completed_orders: int}>
     */
    public function supplierLeadTimes(string $businessId, int $limit = 5): array
    {
        $orders = PurchaseOrder::query()
            ->where('business_id', $businessId)
            ->whereNotNull('sent_at')
            ->with(['supplier:id,name', 'goodsReceivedNotes' => fn ($query) => $query->oldest('received_at')->limit(1)])
            ->get();

        $bySupplier = [];

        foreach ($orders as $po) {
            $firstDelivery = $po->goodsReceivedNotes->first();

            if (! $firstDelivery || ! $po->supplier) {
                continue;
            }

            $leadDays = $po->sent_at->diffInDays($firstDelivery->received_at);

            $bySupplier[$po->supplier_id] ??= ['name' => $po->supplier->name, 'days' => [], 'count' => 0];
            $bySupplier[$po->supplier_id]['days'][] = $leadDays;
            $bySupplier[$po->supplier_id]['count']++;
        }

        return collect($bySupplier)
            ->map(fn ($row, $supplierId) => [
                'supplier_id' => $supplierId,
                'name' => $row['name'],
                'average_lead_time_days' => round(array_sum($row['days']) / count($row['days']), 1),
                'completed_orders' => $row['count'],
            ])
            ->sortBy('average_lead_time_days')
            ->take($limit)
            ->values()
            ->all();
    }
}
