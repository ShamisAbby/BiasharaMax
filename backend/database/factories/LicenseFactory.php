<?php

namespace Database\Factories;

use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Services\LicenseService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    protected $model = License::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Takes the prefix from the service rather than repeating it,
            // so the factory cannot quietly drift from what the app issues.
            'license_key' => LicenseService::KEY_PREFIX.'-'.collect(range(1, 4))->map(fn () => Str::upper(Str::random(4)))->implode('-'),
            'type' => License::TYPE_PROFESSIONAL,
            'max_devices' => 3,
            'status' => License::STATUS_ACTIVE,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'maintenance_expires_at' => now()->addYear(),
            'offline_activation_allowed' => true,
            'cloud_sync_enabled' => false,
        ];
    }
}
