<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Licensing\Models\LicenseDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LicenseDevice
 */
class LicenseDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hardware_fingerprint' => $this->hardware_fingerprint,
            'machine_name' => $this->machine_name,
            'ip_address' => $this->ip_address,
            'is_active' => $this->isActive(),
            'activated_at' => $this->activated_at,
            'last_seen_at' => $this->last_seen_at,
            'deactivated_at' => $this->deactivated_at,
        ];
    }
}
