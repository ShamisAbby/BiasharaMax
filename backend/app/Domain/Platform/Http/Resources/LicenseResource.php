<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Licensing\Models\License;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin License
 */
class LicenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_key' => $this->license_key,
            'type' => $this->type,
            'status' => $this->status,
            'max_devices' => $this->max_devices,
            'active_devices_count' => $this->whenCounted('activeDevices'),
            'issued_at' => $this->issued_at,
            'expires_at' => $this->expires_at,
            'maintenance_expires_at' => $this->maintenance_expires_at,
            'is_maintenance_active' => $this->isMaintenanceActive(),
            'offline_activation_allowed' => $this->offline_activation_allowed,
            'cloud_sync_enabled' => $this->cloud_sync_enabled,
            'notes' => $this->notes,
            'revoked_at' => $this->revoked_at,
            'revoked_reason' => $this->revoked_reason,
            'business' => $this->whenLoaded('business', fn () => $this->business ? [
                'id' => $this->business->id,
                'name' => $this->business->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
