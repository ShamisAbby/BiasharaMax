<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Exceptions\GatewayNotConfiguredException;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentGatewayLog;

class PaymentGatewayService
{
    public function __construct(
        private readonly GatewayDriverResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentGateway
    {
        return PaymentGateway::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentGateway $gateway, array $data): PaymentGateway
    {
        $gateway->update($data);

        return $gateway->refresh();
    }

    public function enable(PaymentGateway $gateway): PaymentGateway
    {
        $gateway->update(['is_enabled' => true]);

        return $gateway->refresh();
    }

    public function disable(PaymentGateway $gateway): PaymentGateway
    {
        $gateway->update(['is_enabled' => false]);

        return $gateway->refresh();
    }

    /**
     * A lightweight reachability probe: attempts the driver's verify()
     * call against a deliberately invalid id. A configured, reachable
     * gateway responds (even with "not found"); an unreachable one
     * times out or connection-refuses. This is honest best-effort health
     * signal, not a guarantee the gateway will accept real charges.
     */
    public function checkHealth(PaymentGateway $gateway): PaymentGateway
    {
        if (! $gateway->isConfigured()) {
            $gateway->update(['health_status' => PaymentGateway::HEALTH_UNKNOWN, 'last_health_check_at' => now()]);

            return $gateway->refresh();
        }

        try {
            $this->resolver->resolve($gateway)->verify('health-check-probe');
            $status = PaymentGateway::HEALTH_ONLINE;
        } catch (\Throwable $e) {
            $status = PaymentGateway::HEALTH_OFFLINE;

            PaymentGatewayLog::query()->create([
                'payment_gateway_id' => $gateway->id,
                'direction' => PaymentGatewayLog::DIRECTION_OUTBOUND,
                'event_type' => PaymentGatewayLog::EVENT_HEALTH_CHECK,
                'is_successful' => false,
                'error_message' => $e->getMessage(),
            ]);
        }

        $gateway->update(['health_status' => $status, 'last_health_check_at' => now()]);

        return $gateway->refresh();
    }

    /**
     * @throws GatewayNotConfiguredException
     */
    public function ensureConfigured(PaymentGateway $gateway): void
    {
        if (! $gateway->isConfigured()) {
            throw GatewayNotConfiguredException::forGateway($gateway);
        }
    }
}
