<?php

namespace Database\Factories;

use App\Domain\Security\Models\SecurityAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityAlert>
 */
class SecurityAlertFactory extends Factory
{
    protected $model = SecurityAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SecurityAlert::TYPE_SUSPICIOUS_LOGIN,
            'severity' => SecurityAlert::SEVERITY_LOW,
            'description' => fake()->sentence(),
            'is_resolved' => false,
        ];
    }
}
