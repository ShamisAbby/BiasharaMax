<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Integrations\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Integration
 */
class IntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            'provider' => $this->provider,
            'is_enabled' => $this->is_enabled,
            'is_configured' => $this->isConfigured(),
            'mode' => $this->mode,
            'credential_keys' => array_keys($this->credentials ?? []),
            'webhook_url' => $this->webhook_url,
            'last_tested_at' => $this->last_tested_at,
            'last_test_result' => $this->last_test_result,
            'documentation_url' => $this->documentation_url,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}
