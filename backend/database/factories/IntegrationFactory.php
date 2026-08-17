<?php

namespace Database\Factories;

use App\Domain\Integrations\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => Integration::CATEGORY_CUSTOM,
            'provider' => 'custom',
            'is_enabled' => false,
            'mode' => Integration::MODE_SANDBOX,
            'sort_order' => 0,
        ];
    }
}
