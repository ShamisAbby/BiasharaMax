<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Support\Carbon;

/**
 * Every figure here is computed live from real sale_returns rows.
 */
class SaleReturnDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $base = SaleReturn::query()->where('business_id', $businessId);

        return [
            'today_returns_count' => (clone $base)->whereDate('created_at', Carbon::today())->count(),
            'today_return_value' => (float) (clone $base)->whereDate('created_at', Carbon::today())->sum('refund_amount'),
            'refund_amount_this_month' => (float) (clone $base)
                ->where('status', SaleReturn::STATUS_APPROVED)
                ->whereBetween('approved_at', [$monthStart, $monthEnd])
                ->sum('refund_amount'),
            'pending_returns_count' => (clone $base)->where('status', SaleReturn::STATUS_PENDING)->count(),
            'approved_returns_count' => (clone $base)->where('status', SaleReturn::STATUS_APPROVED)->count(),
            'rejected_returns_count' => (clone $base)->where('status', SaleReturn::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @return array<int, array{reason: string, count: int}>
     */
    public function reasonBreakdown(string $businessId): array
    {
        return SaleReturn::query()
            ->where('business_id', $businessId)
            ->selectRaw('reason, count(*) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['reason' => $row->reason, 'count' => (int) $row->total])
            ->all();
    }

    /**
     * @return array<int, array{id: string, return_number: string, sale_number: string, status: string, refund_amount: float, created_at: string}>
     */
    public function recentReturns(string $businessId, int $limit = 5): array
    {
        return SaleReturn::query()
            ->where('business_id', $businessId)
            ->with('sale:id,sale_number')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (SaleReturn $return) => [
                'id' => $return->id,
                'return_number' => $return->return_number,
                'sale_number' => $return->sale?->sale_number ?? '—',
                'status' => $return->status,
                'refund_amount' => (float) $return->refund_amount,
                'created_at' => $return->created_at->toIso8601String(),
            ])
            ->all();
    }
}
