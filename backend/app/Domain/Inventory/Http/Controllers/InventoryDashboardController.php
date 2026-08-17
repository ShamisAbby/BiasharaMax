<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Services\InventoryDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryDashboardController extends Controller
{
    public function __invoke(Request $request, InventoryDashboardService $dashboardService): Response
    {
        abort_unless($request->user()->hasPermission('inventory.view'), 403);

        return Inertia::render('Inventory/Dashboard', $dashboardService->summary($request->user()->business_id));
    }
}
