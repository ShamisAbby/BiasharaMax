<?php

namespace Database\Seeders;

use App\Domain\Subscription\Models\SubscriptionPlan;
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
            'type' => SubscriptionPlan::TYPE_STANDARD,
            'description' => 'For single-location shops getting started with digital records.',
            'price_monthly' => 35000,
            'price_quarterly' => 90000,
            'price_yearly' => 320000,
            'price_lifetime' => null,
            'trial_days' => 30,
            'features' => ['1 branch', 'Up to 3 employees', 'POS & Inventory', 'Email support'],
            'sort_order' => 1,
            'max_users' => 3,
            'max_branches' => 1,
            'max_warehouses' => 1,
            'max_products' => 200,
            'max_employees' => 3,
            'max_storage_mb' => 512,
            'max_api_requests_per_day' => 500,
            'max_notifications_per_month' => 50,
            'includes_website' => false,
            'includes_ai' => false,
            'includes_offline_sync' => false,
            'includes_desktop_edition' => false,
            'includes_reports' => true,
        ],
        [
            'name' => 'Growth',
            'slug' => 'growth',
            'type' => SubscriptionPlan::TYPE_STANDARD,
            'description' => 'For growing businesses with multiple branches and staff.',
            'price_monthly' => 80000,
            'price_quarterly' => 220000,
            'price_yearly' => 760000,
            'price_lifetime' => null,
            'trial_days' => 30,
            'features' => ['Up to 5 branches', 'Up to 25 employees', 'POS & Inventory', 'CRM & Debt Management', 'Priority support'],
            'sort_order' => 2,
            'max_users' => 25,
            'max_branches' => 5,
            'max_warehouses' => 10,
            'max_products' => 5000,
            'max_employees' => 25,
            'max_storage_mb' => 5120,
            'max_api_requests_per_day' => 5000,
            'max_notifications_per_month' => 500,
            'includes_website' => true,
            'includes_ai' => false,
            'includes_offline_sync' => true,
            'includes_desktop_edition' => false,
            'includes_reports' => true,
        ],
        [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'type' => SubscriptionPlan::TYPE_ENTERPRISE,
            'description' => 'For large or multi-branch operations that need full platform access.',
            'price_monthly' => 170000,
            'price_quarterly' => 470000,
            'price_yearly' => 1650000,
            'price_lifetime' => null,
            'trial_days' => 30,
            'features' => ['Unlimited branches', 'Unlimited employees', 'Full platform access', 'Dedicated support', 'API access'],
            'sort_order' => 3,
            'max_users' => null,
            'max_branches' => null,
            'max_warehouses' => null,
            'max_products' => null,
            'max_employees' => null,
            'max_storage_mb' => null,
            'max_api_requests_per_day' => null,
            'max_notifications_per_month' => null,
            'includes_website' => true,
            'includes_ai' => true,
            'includes_offline_sync' => true,
            'includes_desktop_edition' => true,
            'includes_reports' => true,
        ],
        [
            'name' => 'Lifetime',
            'slug' => 'lifetime',
            'type' => SubscriptionPlan::TYPE_STANDARD,
            'description' => 'One-time payment, full Growth-tier features, no recurring billing.',
            'price_monthly' => 0,
            'price_quarterly' => 0,
            'price_yearly' => 0,
            'price_lifetime' => 2500000,
            'trial_days' => 30,
            'features' => ['Up to 5 branches', 'Up to 25 employees', 'POS & Inventory', 'CRM & Debt Management', 'One-time payment'],
            'sort_order' => 4,
            'max_users' => 25,
            'max_branches' => 5,
            'max_warehouses' => 10,
            'max_products' => 5000,
            'max_employees' => 25,
            'max_storage_mb' => 5120,
            'max_api_requests_per_day' => 5000,
            'max_notifications_per_month' => 500,
            'includes_website' => true,
            'includes_ai' => false,
            'includes_offline_sync' => true,
            'includes_desktop_edition' => false,
            'includes_reports' => true,
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
