<?php

namespace Database\Factories;

use App\Domain\Developer\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'url' => fake()->url(),
            'events' => ['business.created'],
            'secret' => Str::random(32),
            'is_active' => true,
        ];
    }
}
