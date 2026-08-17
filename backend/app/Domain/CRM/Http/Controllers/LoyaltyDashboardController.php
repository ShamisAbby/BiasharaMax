<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Services\LoyaltyDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyDashboardController extends Controller
{
    public function __invoke(Request $request, LoyaltyDashboardService $dashboardService): Response
    {
        abort_unless($request->user()->hasPermission('crm.view'), 403);

        $businessId = $request->user()->business_id;

        return Inertia::render('Crm/Loyalty/Dashboard', [
            'summary' => $dashboardService->summary($businessId),
            'topLoyalCustomers' => $dashboardService->topLoyalCustomers($businessId),
            'tierDistribution' => $dashboardService->tierDistribution($businessId),
        ]);
    }
}
