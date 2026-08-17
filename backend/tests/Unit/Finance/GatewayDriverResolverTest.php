<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\Drivers\FlutterwaveDriver;
use App\Domain\Finance\Drivers\GenericHttpGatewayDriver;
use App\Domain\Finance\Drivers\ManualGatewayDriver;
use App\Domain\Finance\Drivers\MpesaDriver;
use App\Domain\Finance\Drivers\PayPalDriver;
use App\Domain\Finance\Drivers\PesapalDriver;
use App\Domain\Finance\Drivers\StripeDriver;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Services\GatewayDriverResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GatewayDriverResolverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: class-string}>
     */
    public static function providerDriverMap(): array
    {
        return [
            'stripe' => [PaymentGateway::PROVIDER_STRIPE, StripeDriver::class],
            'flutterwave' => [PaymentGateway::PROVIDER_FLUTTERWAVE, FlutterwaveDriver::class],
            'pesapal' => [PaymentGateway::PROVIDER_PESAPAL, PesapalDriver::class],
            'paypal' => [PaymentGateway::PROVIDER_PAYPAL, PayPalDriver::class],
            'mpesa' => [PaymentGateway::PROVIDER_MPESA, MpesaDriver::class],
            'cash' => [PaymentGateway::PROVIDER_CASH, ManualGatewayDriver::class],
            'bank_transfer' => [PaymentGateway::PROVIDER_BANK_TRANSFER, ManualGatewayDriver::class],
            'snippe' => [PaymentGateway::PROVIDER_SNIPPE, GenericHttpGatewayDriver::class],
            'airtel_money' => [PaymentGateway::PROVIDER_AIRTEL_MONEY, GenericHttpGatewayDriver::class],
            'custom' => [PaymentGateway::PROVIDER_CUSTOM, GenericHttpGatewayDriver::class],
        ];
    }

    #[DataProvider('providerDriverMap')]
    public function test_resolves_the_correct_driver_for_each_provider(string $provider, string $expectedDriver): void
    {
        $gateway = PaymentGateway::factory()->create(['provider' => $provider]);

        $driver = app(GatewayDriverResolver::class)->resolve($gateway);

        $this->assertInstanceOf($expectedDriver, $driver);
    }

    public function test_unknown_provider_falls_back_to_generic_http_driver(): void
    {
        $gateway = PaymentGateway::factory()->create(['provider' => 'something-new']);

        $driver = app(GatewayDriverResolver::class)->resolve($gateway);

        $this->assertInstanceOf(GenericHttpGatewayDriver::class, $driver);
    }

    public function test_manual_driver_charge_always_succeeds_without_network_calls(): void
    {
        $gateway = PaymentGateway::factory()->create(['provider' => PaymentGateway::PROVIDER_CASH]);
        $transaction = \App\Domain\Finance\Models\PaymentTransaction::factory()->make(['business_id' => '01950000-0000-7000-8000-000000000000']);

        $driver = app(GatewayDriverResolver::class)->resolve($gateway);
        $result = $driver->charge($transaction);

        $this->assertTrue($result['successful']);
    }
}
