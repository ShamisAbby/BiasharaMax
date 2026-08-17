<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Finance\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentGateway
 */
class PaymentGatewayResource extends JsonResource
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
            'provider' => $this->provider,
            'is_enabled' => $this->is_enabled,
            'is_configured' => $this->isConfigured(),
            'mode' => $this->mode,
            'credential_keys' => array_keys($this->credentials ?? []),
            'webhook_url' => $this->webhook_url,
            'has_webhook_secret' => filled($this->webhook_secret),
            'supported_currencies' => $this->supported_currencies,
            'supported_countries' => $this->supported_countries,
            'fee_percentage' => $this->fee_percentage,
            'fee_fixed' => $this->fee_fixed,
            'priority' => $this->priority,
            'health_status' => $this->health_status,
            'last_health_check_at' => $this->last_health_check_at,
            'documentation_url' => $this->documentation_url,
            'sort_order' => $this->sort_order,
            'transactions_count' => $this->whenCounted('transactions'),
            'created_at' => $this->created_at,
        ];
    }
}
