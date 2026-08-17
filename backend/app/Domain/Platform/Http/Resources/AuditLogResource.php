<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Shared\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module,
            'action' => $this->action,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'auditable_type' => $this->auditable_type ? class_basename($this->auditable_type) : null,
            'auditable_id' => $this->auditable_id,
            'business' => $this->whenLoaded('business', fn () => $this->business ? [
                'id' => $this->business->id,
                'name' => $this->business->name,
            ] : null),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'browser' => $this->browser,
            'operating_system' => $this->operating_system,
            'device_type' => $this->device_type,
            'country' => $this->country,
            'risk_level' => $this->risk_level,
            'created_at' => $this->created_at,
        ];
    }
}
