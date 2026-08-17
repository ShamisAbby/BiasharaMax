<?php

namespace Database\Factories;

use App\Domain\RBAC\Models\PlatformRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformRole>
 */
class PlatformRoleFactory extends Factory
{
    protected $model = PlatformRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'slug' => fake()->unique()->slug(2),
            'is_system' => false,
            'description' => fake()->sentence(),
        ];
    }
}
