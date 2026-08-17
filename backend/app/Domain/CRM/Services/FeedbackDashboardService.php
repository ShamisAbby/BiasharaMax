<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\CustomerFeedback;
use Illuminate\Support\Carbon;

/**
 * Every figure here is computed live from real customer_feedback rows.
 */
class FeedbackDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $base = CustomerFeedback::query()->where('business_id', $businessId);

        return [
            'total_feedback' => (clone $base)->count(),
            'open_count' => (clone $base)->where('status', CustomerFeedback::STATUS_OPEN)->count(),
            'pending_count' => (clone $base)->where('status', CustomerFeedback::STATUS_PENDING)->count(),
            'resolved_count' => (clone $base)->where('status', CustomerFeedback::STATUS_RESOLVED)->count(),
            'complaints_this_month' => (clone $base)
                ->where('type', CustomerFeedback::TYPE_COMPLAINT)
                ->where('created_at', '>=', $monthStart)
                ->count(),
            'average_rating' => round((float) (clone $base)->whereNotNull('rating')->avg('rating'), 1),
        ];
    }

    /**
     * @return array<int, array{type: string, count: int}>
     */
    public function typeBreakdown(string $businessId): array
    {
        return CustomerFeedback::query()
            ->where('business_id', $businessId)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['type' => $row->type, 'count' => (int) $row->total])
            ->all();
    }
}
