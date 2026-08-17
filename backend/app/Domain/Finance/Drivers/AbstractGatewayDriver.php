<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Contracts\PaymentGatewayDriver;
use App\Domain\Finance\Exceptions\GatewayNotConfiguredException;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentGatewayLog;
use App\Domain\Finance\Models\PaymentTransaction;

abstract class AbstractGatewayDriver implements PaymentGatewayDriver
{
    public function __construct(protected readonly PaymentGateway $gateway) {}

    protected function ensureConfigured(): void
    {
        if (! $this->gateway->isConfigured()) {
            throw GatewayNotConfiguredException::forGateway($this->gateway);
        }
    }

    protected function credential(string $key): ?string
    {
        return $this->gateway->credentials[$key] ?? null;
    }

    protected function baseUrl(string $sandboxUrl, string $productionUrl): string
    {
        return $this->gateway->mode === PaymentGateway::MODE_PRODUCTION ? $productionUrl : $sandboxUrl;
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     */
    protected function log(
        string $eventType,
        array $request,
        array $response,
        bool $successful,
        ?int $statusCode = null,
        ?string $error = null,
        ?PaymentTransaction $transaction = null,
    ): void {
        PaymentGatewayLog::query()->create([
            'payment_gateway_id' => $this->gateway->id,
            'payment_transaction_id' => $transaction?->id,
            'direction' => PaymentGatewayLog::DIRECTION_OUTBOUND,
            'event_type' => $eventType,
            'request_payload' => $this->redact($request),
            'response_payload' => $response,
            'status_code' => $statusCode,
            'is_successful' => $successful,
            'error_message' => $error,
        ]);
    }

    /**
     * Never persist secrets to the log table, even on the request side.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function redact(array $payload): array
    {
        foreach (['secret_key', 'api_key', 'api_secret', 'password', 'authorization', 'token'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '••••••••';
            }
        }

        return $payload;
    }
}
