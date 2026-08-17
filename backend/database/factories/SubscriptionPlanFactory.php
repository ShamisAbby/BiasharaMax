<?php

namespace Database\Factories;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'type' => 'standard',
            'description' => fake()->sentence(),
            'price_monthly' => fake()->numberBetween(1000, 5000),
            'price_quarterly' => fake()->numberBetween(3000, 14000),
            'price_yearly' => fake()->numberBetween(10000, 50000),
            'price_lifetime' => fake()->numberBetween(50000, 200000),
            'trial_days' => 30,
            'features' => ['Feature A', 'Feature B'],
            'is_active' => true,
            'sort_order' => 0,
            // Generous defaults so unrelated tests/seeds don't trip plan
            // limits by accident — tests covering limit enforcement set
            // these explicitly via SubscriptionPlan::factory()->create([...]).
            'max_users' => 100,
            'max_branches' => 100,
            'max_warehouses' => 100,
            'max_products' => 100000,
            'max_employees' => 100,
            'max_storage_mb' => 102400,
            'max_api_requests_per_day' => 100000,
            'max_notifications_per_month' => 10000,
            'includes_website' => false,
            'includes_ai' => false,
            'includes_offline_sync' => false,
            'includes_desktop_edition' => false,
            'includes_reports' => true,
        ];
    }
}
