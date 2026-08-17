<?php

namespace Database\Factories;

use App\Domain\Notifications\Models\NotificationCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationCampaign>
 */
class NotificationCampaignFactory extends Factory
{
    protected $model = NotificationCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'channel' => 'email',
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'audience_type' => NotificationCampaign::AUDIENCE_ALL_BUSINESSES,
            'status' => NotificationCampaign::STATUS_DRAFT,
        ];
    }
}
