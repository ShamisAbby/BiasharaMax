<?php

namespace Database\Seeders;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Three plans, one product, three lengths.
 *
 * This replaces four feature tiers (Starter/Growth/Enterprise/Lifetime)
 * that each carried monthly, quarterly and yearly prices. Everyone now
 * gets the same capabilities and chooses only how long to buy for, which
 * is a commercial decision rather than a technical one — see the note on
 * limits below for the part that is not purely cosmetic.
 *
 * Prices are derived from the old Starter tier, not invented: it already
 * priced a quarter at 90,000 TZS and a year at 320,000 TZS, so those carry
 * over as the 3 and 12-month figures. Only the 6-month price is new, sat
 * between the two with a discount that keeps the longer term the better
 * deal. All three are editable from the platform admin, which is where
 * they should be corrected — not here.
 *
 * The 30-day free trial is not one of these plans. It is a separate choice
 * at signup that grants full access for `trial_days` and then requires one
 * of these to be bought.
 */
class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Limits are identical across all three, deliberately.
     *
     * The old tiers gated branches, employees and products; selling by
     * duration means a three-month customer and a twelve-month customer
     * get the same product. Keeping the columns populated (rather than
     * null) matters because the enforcement code reads them and treats
     * null as unlimited — so leaving them empty would silently remove
     * every cap instead of applying a generous one.
     *
     * @var array<string, mixed>
     */
    private const SHARED = [
        'type' => SubscriptionPlan::TYPE_STANDARD,
        'trial_days' => 30,
        'max_users' => 25,
        'max_branches' => 5,
        'max_warehouses' => 5,
        'max_products' => 10000,
        'max_employees' => 25,
        'max_storage_mb' => 5120,
        'max_api_requests_per_day' => 5000,
        'max_notifications_per_month' => 500,
        'includes_website' => true,
        'includes_ai' => false,
        'includes_offline_sync' => true,
        'includes_desktop_edition' => true,
        'includes_reports' => true,
        'features' => [
            'POS, inventory and accounting',
            'Up to 5 branches and 25 staff',
            'Desktop till with offline sync',
            'Online storefront',
            'WhatsApp and email support',
        ],
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    private const PLANS = [
        [
            'name' => '3 Months',
            'slug' => 'quarterly',
            'description' => 'Three months of full access. Good for trying the system through a full season.',
            'duration_months' => 3,
            'price' => 90000,
            'sort_order' => 1,
        ],
        [
            'name' => '6 Months',
            'slug' => 'half-yearly',
            'description' => 'Six months of full access, at a lower monthly rate than the three-month plan.',
            'duration_months' => 6,
            'price' => 170000,
            'sort_order' => 2,
        ],
        [
            'name' => '12 Months',
            'slug' => 'yearly',
            'description' => 'A full year of full access, at the lowest monthly rate.',
            'duration_months' => 12,
            'price' => 320000,
            'sort_order' => 3,
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            $attributes = $plan + self::SHARED + ['is_active' => true];

            // The legacy price columns are still read by `priceFor()` and by
            // existing subscription rows, so they are kept consistent with
            // the new single price rather than left at whatever the old
            // tiers had. A stale 35,000 in `price_monthly` next to a 90,000
            // `price` is the kind of disagreement that surfaces months
            // later as an invoice nobody can explain.
            $attributes['price_monthly'] = round($plan['price'] / $plan['duration_months'], 2);
            $attributes['price_quarterly'] = $plan['duration_months'] === 3 ? $plan['price'] : 0;
            $attributes['price_yearly'] = $plan['duration_months'] === 12 ? $plan['price'] : 0;

            SubscriptionPlan::query()->updateOrCreate(['slug' => $plan['slug']], $attributes);
        }

        // The old tiers are retired, not deleted. Existing businesses still
        // point at them through `subscriptions.subscription_plan_id`, and
        // removing the row would orphan those and break every screen that
        // renders a plan name. Deactivating hides them from signup while
        // leaving history intact.
        SubscriptionPlan::query()
            ->whereNotIn('slug', array_column(self::PLANS, 'slug'))
            ->update(['is_active' => false]);
    }
}
