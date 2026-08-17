<?php

namespace App\Domain\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Purchasing\Services\PurchasingDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchasingDashboardController extends Controller
{
    public function __invoke(Request $request, PurchasingDashboardService $dashboardService): Response
    {
        abort_unless($request->user()->hasPermission('purchase_orders.view'), 403);

        $businessId = $request->user()->business_id;

        return Inertia::render('Purchasing/Dashboard', [
            'summary' => $dashboardService->summary($businessId),
            'trend' => $dashboardService->trend($businessId),
            'topSuppliers' => $dashboardService->topSuppliers($businessId),
            'recentPurchaseOrders' => $dashboardService->recentPurchaseOrders($businessId),
            'recentDeliveries' => $dashboardService->recentDeliveries($businessId),
            'supplierLeadTimes' => $dashboardService->supplierLeadTimes($businessId),
        ]);
    }
}
