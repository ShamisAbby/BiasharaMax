<?php

namespace Database\Factories;

use App\Domain\Localization\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->countryCode(),
            'name' => fake()->unique()->country(),
            'is_active' => true,
        ];
    }
}
