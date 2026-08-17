<?php

namespace Database\Factories;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<PlatformUser>
 */
class PlatformUserFactory extends Factory
{
    protected $model = PlatformUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ];
    }
}
