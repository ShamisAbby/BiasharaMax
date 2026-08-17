<?php

namespace Database\Factories;

use App\Domain\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'channel' => 'email',
            'category' => NotificationTemplate::CATEGORY_CUSTOM,
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
