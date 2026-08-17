<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Licensing\Models\LicenseActivationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LicenseActivationLog
 */
class LicenseActivationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'result' => $this->result,
            'reason' => $this->reason,
            'ip_address' => $this->ip_address,
            'device' => $this->whenLoaded('device', fn () => $this->device ? [
                'machine_name' => $this->device->machine_name,
                'hardware_fingerprint' => $this->device->hardware_fingerprint,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
