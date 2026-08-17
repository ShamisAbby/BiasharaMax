<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Accounting\Services\FinancialReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingDashboardController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $reportService): Response
    {
        abort_unless($request->user()->hasPermission('accounting.view'), 403);

        $businessId = $request->user()->business_id;

        return Inertia::render('Accounting/Dashboard', [
            'summary' => $reportService->summary($businessId),
            'profitTrend' => $reportService->profitTrend($businessId),
        ]);
    }
}
