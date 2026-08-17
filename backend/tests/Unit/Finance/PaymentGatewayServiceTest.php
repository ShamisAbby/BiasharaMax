<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentGatewayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaymentGatewayService::class);
    }

    public function test_create_persists_a_gateway(): void
    {
        $gateway = $this->service->create([
            'name' => 'Test Stripe',
            'slug' => 'test-stripe',
            'provider' => PaymentGateway::PROVIDER_STRIPE,
            'mode' => PaymentGateway::MODE_SANDBOX,
        ]);

        $this->assertDatabaseHas('payment_gateways', ['slug' => 'test-stripe']);
        $this->assertFalse($gateway->is_enabled);
    }

    public function test_enable_and_disable_toggle_status(): void
    {
        $gateway = PaymentGateway::factory()->create(['is_enabled' => false]);

        $this->service->enable($gateway);
        $this->assertTrue($gateway->fresh()->is_enabled);

        $this->service->disable($gateway);
        $this->assertFalse($gateway->fresh()->is_enabled);
    }

    public function test_health_check_on_an_unconfigured_gateway_reports_unknown(): void
    {
        $gateway = PaymentGateway::factory()->create(['is_enabled' => false, 'credentials' => null]);

        $this->service->checkHealth($gateway);

        $this->assertSame(PaymentGateway::HEALTH_UNKNOWN, $gateway->fresh()->health_status);
        $this->assertNotNull($gateway->fresh()->last_health_check_at);
    }

    public function test_health_check_on_a_configured_but_unreachable_gateway_reports_offline_and_logs(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'is_enabled' => true,
            'provider' => PaymentGateway::PROVIDER_CUSTOM,
            'credentials' => ['api_key' => 'test', 'verify_endpoint' => 'http://localhost:1/unreachable'],
        ]);

        $this->service->checkHealth($gateway);

        $this->assertSame(PaymentGateway::HEALTH_OFFLINE, $gateway->fresh()->health_status);
        $this->assertDatabaseHas('payment_gateway_logs', [
            'payment_gateway_id' => $gateway->id,
            'is_successful' => false,
        ]);
    }

    public function test_ensure_configured_throws_for_an_unconfigured_gateway(): void
    {
        $gateway = PaymentGateway::factory()->create(['is_enabled' => false, 'credentials' => null]);

        $this->expectException(\App\Domain\Finance\Exceptions\GatewayNotConfiguredException::class);

        $this->service->ensureConfigured($gateway);
    }
}
