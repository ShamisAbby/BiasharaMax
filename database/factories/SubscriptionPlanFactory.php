<?php

namespace Database\Factories;

use App\Modules\Subscription\Models\SubscriptionPlan;
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
            'description' => fake()->sentence(),
            'price_monthly' => fake()->numberBetween(1000, 5000),
            'price_quarterly' => fake()->numberBetween(3000, 14000),
            'price_yearly' => fake()->numberBetween(10000, 50000),
            'trial_days' => 30,
            'features' => ['Feature A', 'Feature B'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
