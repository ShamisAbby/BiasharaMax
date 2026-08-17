<?php

namespace Tests\Unit\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private IntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(IntegrationService::class);
    }

    public function test_test_connection_writes_a_log_and_updates_the_integration_on_success(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response(['ok' => true, 'team' => 'Acme', 'user' => 'bot'], 200),
        ]);

        $integration = Integration::factory()->create([
            'category' => Integration::CATEGORY_COMMUNICATION,
            'provider' => 'slack',
            'is_enabled' => true,
            'credentials' => ['bot_token' => 'xoxb-test'],
        ]);

        $log = $this->service->testConnection($integration);

        $this->assertTrue($log->is_successful);
        $this->assertDatabaseHas('integration_logs', [
            'integration_id' => $integration->id,
            'is_successful' => true,
        ]);

        $integration->refresh();
        $this->assertNotNull($integration->last_tested_at);
        $this->assertSame(Integration::TEST_RESULT_SUCCESS, $integration->last_test_result);
    }

    public function test_test_connection_writes_a_log_and_updates_the_integration_on_failure(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response(['ok' => false, 'error' => 'invalid_auth'], 401),
        ]);

        $integration = Integration::factory()->create([
            'category' => Integration::CATEGORY_COMMUNICATION,
            'provider' => 'slack',
            'is_enabled' => true,
            'credentials' => ['bot_token' => 'xoxb-bad'],
        ]);

        $log = $this->service->testConnection($integration);

        $this->assertFalse($log->is_successful);
        $this->assertSame('invalid_auth', $log->error_message);

        $integration->refresh();
        $this->assertNotNull($integration->last_tested_at);
        $this->assertSame(Integration::TEST_RESULT_FAILED, $integration->last_test_result);
    }

    public function test_test_connection_handles_an_unconfigured_integration_without_throwing(): void
    {
        $integration = Integration::factory()->create([
            'category' => Integration::CATEGORY_COMMUNICATION,
            'provider' => 'slack',
            'is_enabled' => false,
            'credentials' => null,
        ]);

        $log = $this->service->testConnection($integration);

        $this->assertFalse($log->is_successful);
        $this->assertSame(Integration::TEST_RESULT_FAILED, $integration->fresh()->last_test_result);
    }

    public function test_enable_and_disable_toggle_status(): void
    {
        $integration = Integration::factory()->create(['is_enabled' => false]);

        $this->service->enable($integration);
        $this->assertTrue($integration->fresh()->is_enabled);

        $this->service->disable($integration);
        $this->assertFalse($integration->fresh()->is_enabled);
    }
}
