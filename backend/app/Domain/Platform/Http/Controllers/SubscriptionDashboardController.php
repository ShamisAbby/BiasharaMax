<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Subscription\Services\SubscriptionAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionDashboardController extends Controller
{
    public function __invoke(SubscriptionAnalyticsService $analytics): Response
    {
        $dashboard = $analytics->dashboard();

        return Inertia::render('Platform/Subscriptions/Dashboard', [
            'revenue' => $dashboard['revenue'],
            'trial' => $dashboard['trial'],
            'subscribers' => $dashboard['subscribers'],
            'monthlyRevenue' => $dashboard['monthly_revenue'],
            'expiringSoon' => $dashboard['expiring_soon']->map(fn ($subscription) => [
                'id' => $subscription->id,
                'business_name' => $subscription->business?->name,
                'plan_name' => $subscription->plan?->name,
                'current_period_end' => $subscription->current_period_end,
            ]),
        ]);
    }
}
