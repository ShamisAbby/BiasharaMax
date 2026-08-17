<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Domain\Payroll\Services\HrDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrDashboardController
{
    public function __construct(
        private readonly HrDashboardService $dashboard,
    ) {}

    public function __invoke(Request $request): Response
    {
        // Headcount, payroll cost and salary aggregates — not something
        // every employee should be able to open. This was the last
        // ungated read in the module.
        $user = $request->user();

        abort_unless(
            $user->hasPermission('payroll.view')
                || $user->hasPermission('payroll.manage'),
            403,
        );

        $business = $user->business;

        return Inertia::render('Payroll/Dashboard', [
            'summary' => $business ? $this->dashboard->summary($business->id) : null,
        ]);
    }
}
