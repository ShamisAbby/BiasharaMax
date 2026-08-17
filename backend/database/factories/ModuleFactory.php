<?php

namespace Database\Factories;

use App\Domain\ModuleManagement\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'version' => '1.0.0',
            'category' => 'Core',
            'is_premium' => false,
            'status' => Module::STATUS_ACTIVE,
            'visibility' => Module::VISIBILITY_PUBLIC,
            'is_desktop_supported' => false,
            'is_cloud_supported' => true,
            'is_hybrid_supported' => false,
            'sort_order' => 0,
        ];
    }
}
