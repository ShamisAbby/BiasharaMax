<?php

namespace App\Domain\Finance\Exceptions;

use App\Domain\Finance\Models\PaymentGateway;
use RuntimeException;

class GatewayNotConfiguredException extends RuntimeException
{
    public static function forGateway(PaymentGateway $gateway): self
    {
        return new self("Gateway \"{$gateway->name}\" is not enabled or has no credentials configured.");
    }
}
