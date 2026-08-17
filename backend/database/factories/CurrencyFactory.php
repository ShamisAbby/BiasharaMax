<?php

namespace Database\Factories;

use App\Domain\Localization\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->currencyCode(),
            'name' => fake()->words(2, true),
            'symbol' => '$',
            'exchange_rate_to_base' => 1,
            'is_base' => false,
            'is_active' => true,
        ];
    }
}
