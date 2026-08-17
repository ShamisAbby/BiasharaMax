<?php

namespace Database\Factories;

use App\Domain\Notifications\Models\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    protected $model = NotificationChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'channel' => NotificationChannel::CHANNEL_EMAIL,
            'provider' => 'smtp',
            'is_enabled' => false,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
