<?php

namespace Database\Factories;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Support\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => 'TKT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'opened_by_type' => 'platform_user',
            'opened_by_id' => PlatformUser::factory(),
            'category' => 'other',
            'priority' => SupportTicket::PRIORITY_MEDIUM,
            'status' => SupportTicket::STATUS_OPEN,
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
        ];
    }
}
