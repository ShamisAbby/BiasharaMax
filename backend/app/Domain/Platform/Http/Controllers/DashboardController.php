<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\FinanceAnalyticsService;
use App\Domain\Platform\Services\PlatformAnalyticsService;
use App\Domain\Platform\Services\PlatformPulseService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(PlatformAnalyticsService $analytics, PlatformPulseService $pulse, FinanceAnalyticsService $finance): Response
    {
        return Inertia::render('Platform/Dashboard', [
            'overview' => $analytics->overview(),
            'businessRegistrationTrend' => $analytics->businessRegistrationTrend(),
            'subscriptionGrowth' => $analytics->subscriptionGrowth(),
            'topBusinessTypes' => $analytics->topBusinessTypes(),
            'countryDistribution' => $analytics->countryDistribution(),
            'subscriptionStatusBreakdown' => $analytics->subscriptionStatusBreakdown(),
            'queueSnapshot' => $analytics->queueSnapshot(),
            'kpis' => $pulse->kpis(),
            'businessPulse' => $pulse->businessPulse(),
            'liveActivity' => $pulse->liveActivity(),
            'revenueTrend' => $finance->monthlyTrend(),
            'paymentMethods' => $finance->topPaymentMethods(5),
            'platformVersion' => config('app.version'),
        ]);
    }
}
