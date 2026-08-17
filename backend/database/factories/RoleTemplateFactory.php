<?php

namespace Database\Factories;

use App\Domain\RBAC\Models\RoleTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleTemplate>
 */
class RoleTemplateFactory extends Factory
{
    protected $model = RoleTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle().' Template',
            'slug' => fake()->unique()->slug(2),
            'scope' => 'tenant',
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }
}
