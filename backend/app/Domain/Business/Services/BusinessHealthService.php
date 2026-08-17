<?php

namespace App\Domain\Business\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Services\InventoryDashboardService;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\SalesDashboardService;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * A real, explainable Business Health score (0-100) computed entirely
 * from data the platform already owns — inventory health, sales
 * momentum, customer debt exposure, and subscription standing. Same
 * deduction-style approach as the Platform's SystemMetricsService /
 * SecurityScoreService: every point lost maps to a named, real signal.
 * Not AI, not a black box — and the recommendations are plain rule-based
 * sentences generated from those same real numbers, never fabricated text.
 */
class BusinessHealthService
{
    public function __construct(
        private readonly InventoryDashboardService $inventoryDashboard,
        private readonly SalesDashboardService $salesDashboard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compute(Business $business): array
    {
        $inventory = $this->inventoryDashboard->summary($business->id);
        $salesSummary = $this->salesDashboard->summary($business->id);
        $subscription = $business->subscription;

        $weekRevenue = $this->revenueSince($business->id, Carbon::now()->subDays(7));
        $previousWeekRevenue = $this->revenueBetween(
            $business->id,
            Carbon::now()->subDays(14),
            Carbon::now()->subDays(7),
        );
        $salesChangePercent = $this->percentChange($previousWeekRevenue, $weekRevenue);

        $debtRatio = $weekRevenue > 0
            ? ($salesSummary['outstanding_credit'] / max($weekRevenue, 1)) * 100
            : ($salesSummary['outstanding_credit'] > 0 ? 100 : 0);

        $signals = [];
        $score = 100;

        // Inventory health (real, from InventoryDashboardService) — weight ~30%
        $inventoryPenalty = (int) round((100 - $inventory['health_score']) * 0.3);
        if ($inventoryPenalty > 0) {
            $score -= $inventoryPenalty;
            $signals[] = ['label' => 'Inventory health', 'deduction' => $inventoryPenalty];
        }

        // Sales momentum vs the prior week — weight up to 25
        if ($previousWeekRevenue > 0 && $salesChangePercent < 0) {
            $salesPenalty = (int) min(25, round(abs($salesChangePercent) * 0.5));
            $score -= $salesPenalty;
            $signals[] = ['label' => 'Declining sales', 'deduction' => $salesPenalty];
        }

        // Outstanding customer debt relative to recent revenue — weight up to 25
        if ($debtRatio > 20) {
            $debtPenalty = (int) min(25, round(($debtRatio - 20) * 0.3));
            $score -= $debtPenalty;
            $signals[] = ['label' => 'Outstanding customer debt', 'deduction' => $debtPenalty];
        }

        // Subscription standing — weight up to 20
        $subscriptionPenalty = match ($subscription?->status) {
            Subscription::STATUS_PAST_DUE => 15,
            Subscription::STATUS_SUSPENDED => 20,
            default => 0,
        };
        if ($subscriptionPenalty > 0) {
            $score -= $subscriptionPenalty;
            $signals[] = ['label' => 'Subscription needs attention', 'deduction' => $subscriptionPenalty];
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'status' => $this->statusLabel($score),
            'signals' => $signals,
            'recommendations' => $this->recommendations(
                $inventory,
                $salesSummary,
                $salesChangePercent,
                $debtRatio,
                $subscriptionPenalty > 0,
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function recommendations(
        array $inventory,
        array $salesSummary,
        ?float $salesChangePercent,
        float $debtRatio,
        bool $subscriptionNeedsAttention,
    ): array {
        $recommendations = [];

        if ($inventory['out_of_stock_count'] > 0) {
            $recommendations[] = "{$inventory['out_of_stock_count']} product(s) are out of stock — restock soon to avoid lost sales.";
        } elseif ($inventory['low_stock_count'] > 0) {
            $recommendations[] = "{$inventory['low_stock_count']} product(s) are running low — consider reordering.";
        }

        if ($inventory['expiring_soon_count'] > 0) {
            $recommendations[] = "{$inventory['expiring_soon_count']} batch(es) are expiring within 30 days.";
        }

        if ($salesChangePercent !== null && $salesChangePercent < -10) {
            $recommendations[] = 'Sales this week are down '.abs(round($salesChangePercent)).'% compared to last week.';
        }

        if ($debtRatio > 20 && $salesSummary['outstanding_credit'] > 0) {
            $recommendations[] = 'Outstanding customer debt is significant relative to recent revenue — consider following up on overdue balances.';
        }

        if ($subscriptionNeedsAttention) {
            $recommendations[] = 'Your subscription needs attention — check your billing to avoid service interruption.';
        }

        if ($recommendations === []) {
            $recommendations[] = 'Everything looks healthy — keep up the great work!';
        }

        return $recommendations;
    }

    private function statusLabel(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Excellent',
            $score >= 65 => 'Good',
            $score >= 40 => 'Needs Attention',
            default => 'Critical',
        };
    }

    private function revenueSince(string $businessId, Carbon $since): float
    {
        return (float) Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('created_at', '>=', $since)
            ->sum('total_amount');
    }

    private function revenueBetween(string $businessId, Carbon $from, Carbon $to): float
    {
        return (float) Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
