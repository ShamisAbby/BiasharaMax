<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Accounting\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProfitAndLossController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $reportService): Response
    {
        abort_unless($request->user()->hasPermission('accounting.view'), 403);

        $businessId = $request->user()->business_id;

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->value())->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->value())->endOfDay()
            : Carbon::now()->endOfMonth();

        return Inertia::render('Accounting/Reports/ProfitAndLoss', [
            'report' => $reportService->profitAndLoss($businessId, $from, $to),
        ]);
    }
}
