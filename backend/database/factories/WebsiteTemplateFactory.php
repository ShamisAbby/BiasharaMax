<?php

namespace Database\Factories;

use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteTemplate>
 */
class WebsiteTemplateFactory extends Factory
{
    protected $model = WebsiteTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'status' => WebsiteTemplate::STATUS_DRAFT,
            'version' => '1.0.0',
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
