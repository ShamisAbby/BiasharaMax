<?php

namespace Tests\Feature\Platform;

use App\Domain\Finance\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A configured gateway must be enable-able.
 *
 * It was not — for any of the twelve. `isConfigured()` returned
 * `is_enabled && filled($credentials)` while the admin refused to enable a
 * gateway that was not configured, so the two conditions each waited on
 * the other. Saving the correct Snippe keys produced "Disabled · Not
 * configured", exactly as it had before, and no amount of re-entering them
 * changed anything.
 *
 * What made it survive is that both readings of "configured" were
 * reasonable: the admin meant "has keys", the driver meant "ready to take
 * money". Neither was wrong on its own; sharing one method was.
 */
class GatewayCanBeEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_alone_make_a_gateway_configured(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'is_enabled' => false,
            'credentials' => ['api_key' => 'snp_test'],
        ]);

        $this->assertTrue(
            $gateway->isConfigured(),
            'A gateway with credentials reported itself unconfigured, so it could never be enabled.',
        );
    }

    public function test_a_gateway_with_no_credentials_is_not_configured(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'is_enabled' => false,
            'credentials' => [],
        ]);

        $this->assertFalse($gateway->isConfigured());
    }

    /**
     * The other half of the split. Keys are not permission — an operator
     * who switches a gateway off must actually stop it taking money.
     */
    public function test_a_disabled_gateway_is_never_usable(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'is_enabled' => false,
            'credentials' => ['api_key' => 'snp_test'],
        ]);

        $this->assertTrue($gateway->isConfigured());
        $this->assertFalse($gateway->isUsable());
    }

    public function test_enabled_and_credentialled_is_usable(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'is_enabled' => true,
            'credentials' => ['api_key' => 'snp_test'],
        ]);

        $this->assertTrue($gateway->isUsable());
    }

    public function test_enabled_without_credentials_is_not_usable(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'is_enabled' => true,
            'credentials' => [],
        ]);

        $this->assertFalse($gateway->isUsable());
    }
}
