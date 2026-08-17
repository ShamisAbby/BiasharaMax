<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Shared\Support\DateFormatSql;
use Illuminate\Support\Carbon;

/**
 * Real numbers only: every figure here is computed directly from
 * `payment_transactions` rows — no projected/estimated revenue.
 */
class FinanceAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'revenue' => $this->revenueSummary(),
            'transaction_counts' => $this->transactionCounts(),
            'commission' => $this->commissionAndTax(),
            'monthly_growth' => $this->monthlyTrend(),
            'recent_transactions' => $this->recentTransactions(),
            'failed_transactions' => $this->failedTransactions(),
            'pending_transactions' => $this->pendingTransactions(),
            'top_businesses' => $this->topBusinesses(),
            'top_payment_methods' => $this->topPaymentMethods(),
            'gateway_performance' => $this->gatewayPerformance(),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function revenueSummary(): array
    {
        $successful = PaymentTransaction::query()->where('status', PaymentTransaction::STATUS_SUCCESSFUL);

        return [
            'total' => (float) (clone $successful)->sum('amount'),
            'today' => (float) (clone $successful)->whereDate('paid_at', Carbon::today())->sum('amount'),
            'this_month' => (float) (clone $successful)->whereBetween('paid_at', [
                Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(),
            ])->sum('amount'),
            'this_year' => (float) (clone $successful)->whereBetween('paid_at', [
                Carbon::now()->startOfYear(), Carbon::now()->endOfYear(),
            ])->sum('amount'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function transactionCounts(): array
    {
        $counts = PaymentTransaction::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'pending' => (int) ($counts[PaymentTransaction::STATUS_PENDING] ?? 0),
            'successful' => (int) ($counts[PaymentTransaction::STATUS_SUCCESSFUL] ?? 0),
            'failed' => (int) ($counts[PaymentTransaction::STATUS_FAILED] ?? 0),
            'refunded' => (int) ($counts[PaymentTransaction::STATUS_REFUNDED] ?? 0)
                + (int) ($counts[PaymentTransaction::STATUS_PARTIALLY_REFUNDED] ?? 0),
            // No dispute/chargeback webhook handling exists yet for any
            // gateway — this counts transactions explicitly flagged as a
            // dispute in metadata (real query, honestly zero until that
            // integration is built, never fabricated).
            'chargebacks' => PaymentTransaction::query()->where('metadata->dispute', true)->count(),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function commissionAndTax(): array
    {
        $successful = PaymentTransaction::query()->where('status', PaymentTransaction::STATUS_SUCCESSFUL);

        return [
            'commission' => (float) (clone $successful)->sum('commission_amount'),
            'tax_collected' => (float) (clone $successful)->sum('tax_amount'),
            'fees' => (float) (clone $successful)->sum('fee_amount'),
        ];
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    public function monthlyTrend(): array
    {
        $since = Carbon::now()->startOfMonth()->subMonths(11);

        $rows = PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->where('paid_at', '>=', $since)
            ->selectRaw(DateFormatSql::monthly('paid_at')." as month, sum(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $trend = [];

        for ($date = $since->copy(); $date->lte(Carbon::now()); $date->addMonth()) {
            $key = $date->format('Y-m');

            $trend[] = ['label' => $date->format('M Y'), 'amount' => (float) ($rows[$key] ?? 0)];
        }

        return $trend;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PaymentTransaction>
     */
    public function recentTransactions(int $limit = 10)
    {
        return PaymentTransaction::query()
            ->with(['business', 'gateway'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PaymentTransaction>
     */
    public function failedTransactions(int $limit = 10)
    {
        return PaymentTransaction::query()
            ->with(['business', 'gateway'])
            ->where('status', PaymentTransaction::STATUS_FAILED)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PaymentTransaction>
     */
    public function pendingTransactions(int $limit = 10)
    {
        return PaymentTransaction::query()
            ->with(['business', 'gateway'])
            ->whereIn('status', [PaymentTransaction::STATUS_PENDING, PaymentTransaction::STATUS_PROCESSING])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, array{business_id: string, business_name: ?string, total: float}>
     */
    public function topBusinesses(int $limit = 10): array
    {
        return PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->selectRaw('business_id, sum(amount) as total')
            ->groupBy('business_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('business:id,name')
            ->get()
            ->map(fn (PaymentTransaction $row) => [
                'business_id' => $row->business_id,
                'business_name' => $row->business?->name,
                'total' => (float) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array{payment_method: string, total: float, count: int}>
     */
    public function topPaymentMethods(int $limit = 10): array
    {
        return PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, sum(amount) as total, count(*) as count')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{gateway_id: string, gateway_name: string, total: float, successful: int, failed: int, success_rate: float}>
     */
    public function gatewayPerformance(): array
    {
        return PaymentGateway::query()
            ->withCount([
                'transactions as successful_count' => fn ($q) => $q->where('status', PaymentTransaction::STATUS_SUCCESSFUL),
                'transactions as failed_count' => fn ($q) => $q->where('status', PaymentTransaction::STATUS_FAILED),
            ])
            ->get()
            ->map(function (PaymentGateway $gateway) {
                $total = $gateway->successful_count + $gateway->failed_count;

                return [
                    'gateway_id' => $gateway->id,
                    'gateway_name' => $gateway->name,
                    'total' => (float) $gateway->transactions()->where('status', PaymentTransaction::STATUS_SUCCESSFUL)->sum('amount'),
                    'successful' => $gateway->successful_count,
                    'failed' => $gateway->failed_count,
                    'success_rate' => $total > 0 ? round(($gateway->successful_count / $total) * 100, 1) : 0.0,
                ];
            })
            ->all();
    }
}
