<?php

namespace Database\Seeders;

use App\Modules\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const PLANS = [
        [
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'For single-location shops getting started with digital records.',
            'price_monthly' => 1500,
            'price_quarterly' => 4000,
            'price_yearly' => 14000,
            'trial_days' => 30,
            'features' => ['1 branch', 'Up to 3 employees', 'POS & Inventory', 'Email support'],
            'sort_order' => 1,
        ],
        [
            'name' => 'Growth',
            'slug' => 'growth',
            'description' => 'For growing businesses with multiple branches and staff.',
            'price_monthly' => 3500,
            'price_quarterly' => 9500,
            'price_yearly' => 33000,
            'trial_days' => 30,
            'features' => ['Up to 5 branches', 'Up to 25 employees', 'POS & Inventory', 'CRM & Debt Management', 'Priority support'],
            'sort_order' => 2,
        ],
        [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'For large or multi-branch operations that need full platform access.',
            'price_monthly' => 7500,
            'price_quarterly' => 20500,
            'price_yearly' => 72000,
            'trial_days' => 30,
            'features' => ['Unlimited branches', 'Unlimited employees', 'Full platform access', 'Dedicated support', 'API access'],
            'sort_order' => 3,
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan + ['is_active' => true],
            );
        }
    }
}
