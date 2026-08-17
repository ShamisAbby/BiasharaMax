<?php

namespace Database\Factories;

use App\Domain\Business\Models\BusinessType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessType>
 */
class BusinessTypeFactory extends Factory
{
    protected $model = BusinessType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'icon' => 'building-storefront',
            'color' => '#4F46E5',
            'description' => fake()->sentence(),
            'default_currency' => 'TZS',
            'default_tax_rate' => 18.00,
            'inventory_enabled' => true,
            'pos_enabled' => false,
            'accounting_enabled' => false,
            'crm_enabled' => false,
            'website_enabled' => false,
            'online_ordering_enabled' => false,
            'offline_mode_enabled' => false,
            'desktop_edition_enabled' => false,
            'status' => BusinessType::STATUS_ACTIVE,
            'sort_order' => 0,
        ];
    }
}
