<?php

namespace Database\Factories;

use App\Domain\Support\Models\SupportDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportDepartment>
 */
class SupportDepartmentFactory extends Factory
{
    protected $model = SupportDepartment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
