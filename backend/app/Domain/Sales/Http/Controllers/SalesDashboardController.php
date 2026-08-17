<?php

namespace App\Domain\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Sales\Services\SalesDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController extends Controller
{
    public function __invoke(Request $request, SalesDashboardService $dashboardService): Response
    {
        abort_unless($request->user()->hasPermission('sales.view'), 403);

        $businessId = $request->user()->business_id;

        return Inertia::render('Sales/Dashboard', [
            'summary' => $dashboardService->summary($businessId),
            'revenueTrend' => $dashboardService->revenueTrend($businessId),
            'topSellingProducts' => $dashboardService->topSellingProducts($businessId),
            'paymentMethodBreakdown' => $dashboardService->paymentMethodBreakdown($businessId),
        ]);
    }
}
