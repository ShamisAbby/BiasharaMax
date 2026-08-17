<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\FinanceAnalyticsService;
use App\Domain\Platform\Http\Resources\PaymentTransactionResource;
use Inertia\Inertia;
use Inertia\Response;

class FinanceDashboardController extends Controller
{
    public function __invoke(FinanceAnalyticsService $analytics): Response
    {
        $dashboard = $analytics->dashboard();

        return Inertia::render('Platform/Finance/Dashboard', [
            'revenue' => $dashboard['revenue'],
            'transactionCounts' => $dashboard['transaction_counts'],
            'commission' => $dashboard['commission'],
            'monthlyGrowth' => $dashboard['monthly_growth'],
            'recentTransactions' => PaymentTransactionResource::collection($dashboard['recent_transactions']),
            'failedTransactions' => PaymentTransactionResource::collection($dashboard['failed_transactions']),
            'pendingTransactions' => PaymentTransactionResource::collection($dashboard['pending_transactions']),
            'topBusinesses' => $dashboard['top_businesses'],
            'topPaymentMethods' => $dashboard['top_payment_methods'],
            'gatewayPerformance' => $dashboard['gateway_performance'],
        ]);
    }
}
