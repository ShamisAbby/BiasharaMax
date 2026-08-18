<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Contracts\PaymentGatewayDriver;
use App\Domain\Finance\Drivers\FlutterwaveDriver;
use App\Domain\Finance\Drivers\GenericHttpGatewayDriver;
use App\Domain\Finance\Drivers\ManualGatewayDriver;
use App\Domain\Finance\Drivers\MpesaDriver;
use App\Domain\Finance\Drivers\PayPalDriver;
use App\Domain\Finance\Drivers\PesapalDriver;
use App\Domain\Finance\Drivers\SnippeDriver;
use App\Domain\Finance\Drivers\StripeDriver;
use App\Domain\Finance\Models\PaymentGateway;

class GatewayDriverResolver
{
    /**
     * @var array<string, class-string<PaymentGatewayDriver>>
     */
    private const DRIVER_MAP = [
        PaymentGateway::PROVIDER_STRIPE => StripeDriver::class,
        PaymentGateway::PROVIDER_FLUTTERWAVE => FlutterwaveDriver::class,
        PaymentGateway::PROVIDER_PESAPAL => PesapalDriver::class,
        PaymentGateway::PROVIDER_PAYPAL => PayPalDriver::class,
        PaymentGateway::PROVIDER_MPESA => MpesaDriver::class,
        PaymentGateway::PROVIDER_CASH => ManualGatewayDriver::class,
        PaymentGateway::PROVIDER_BANK_TRANSFER => ManualGatewayDriver::class,
        // A real driver now, ported from a production integration —
        // not the generic poster, which guessed at Snippe's contract.
        PaymentGateway::PROVIDER_SNIPPE => SnippeDriver::class,
        PaymentGateway::PROVIDER_AIRTEL_MONEY => GenericHttpGatewayDriver::class,
        PaymentGateway::PROVIDER_TIGO_PESA => GenericHttpGatewayDriver::class,
        PaymentGateway::PROVIDER_HALOPESA => GenericHttpGatewayDriver::class,
        PaymentGateway::PROVIDER_MIXX_BY_YAS => GenericHttpGatewayDriver::class,
        PaymentGateway::PROVIDER_CUSTOM => GenericHttpGatewayDriver::class,
    ];

    public function resolve(PaymentGateway $gateway): PaymentGatewayDriver
    {
        $driverClass = self::DRIVER_MAP[$gateway->provider] ?? GenericHttpGatewayDriver::class;

        return new $driverClass($gateway);
    }
}
