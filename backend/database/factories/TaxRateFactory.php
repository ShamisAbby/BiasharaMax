<?php

namespace Database\Factories;

use App\Domain\Localization\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'rate' => fake()->randomFloat(2, 0, 25),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
