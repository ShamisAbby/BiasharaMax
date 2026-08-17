<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Services\CrmDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmDashboardController extends Controller
{
    public function __invoke(Request $request, CrmDashboardService $dashboardService): Response
    {
        abort_unless($request->user()->hasPermission('crm.view'), 403);

        $businessId = $request->user()->business_id;

        return Inertia::render('Crm/Dashboard', [
            'summary' => $dashboardService->summary($businessId),
            'topCustomers' => $dashboardService->topCustomers($businessId),
            'newCustomersTrend' => $dashboardService->newCustomersTrend($businessId),
        ]);
    }
}
