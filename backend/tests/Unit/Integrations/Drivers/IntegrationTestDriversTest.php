<?php

namespace Tests\Unit\Integrations\Drivers;

use App\Domain\Integrations\Drivers\ClaudeTestDriver;
use App\Domain\Integrations\Drivers\GeminiTestDriver;
use App\Domain\Integrations\Drivers\GenericHttpTestDriver;
use App\Domain\Integrations\Drivers\GoogleMapsTestDriver;
use App\Domain\Integrations\Drivers\OpenAiTestDriver;
use App\Domain\Integrations\Drivers\SlackTestDriver;
use App\Domain\Integrations\Exceptions\IntegrationNotConfiguredException;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationTestDriversTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_driver_succeeds_when_models_endpoint_returns_200(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4o-mini'], ['id' => 'gpt-4o']]], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'openai', 'category' => Integration::CATEGORY_AI,
            'is_enabled' => true, 'credentials' => ['api_key' => 'sk-test'],
        ]);

        $result = (new OpenAiTestDriver($integration))->test();

        $this->assertTrue($result['successful']);
        $this->assertSame(2, $result['response']['model_count']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/models'
            && $request->hasHeader('Authorization', 'Bearer sk-test'));
    }

    public function test_openai_driver_fails_when_models_endpoint_returns_error(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'openai', 'category' => Integration::CATEGORY_AI,
            'is_enabled' => true, 'credentials' => ['api_key' => 'sk-bad'],
        ]);

        $result = (new OpenAiTestDriver($integration))->test();

        $this->assertFalse($result['successful']);
        $this->assertSame('Invalid API key', $result['error']);
    }

    public function test_openai_driver_complete_returns_message_content(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Revenue is trending up.']]],
            ], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'openai', 'category' => Integration::CATEGORY_AI,
            'is_enabled' => true, 'credentials' => ['api_key' => 'sk-test'],
        ]);

        $text = (new OpenAiTestDriver($integration))->complete('Summarize this.');

        $this->assertSame('Revenue is trending up.', $text);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request['model'] === 'gpt-4o-mini'
            && $request['messages'][0]['content'] === 'Summarize this.');
    }

    public function test_claude_driver_treats_400_as_successful_ping(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(['error' => ['message' => 'max_tokens too small']], 400),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'claude', 'category' => Integration::CATEGORY_AI,
            'is_enabled' => true, 'credentials' => ['api_key' => 'claude-key'],
        ]);

        $result = (new ClaudeTestDriver($integration))->test();

        $this->assertTrue($result['successful']);
        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'claude-key')
            && $request->hasHeader('anthropic-version', '2023-06-01'));
    }

    public function test_claude_driver_fails_on_other_error_status(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(['error' => ['message' => 'unauthorized']], 401),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'claude', 'category' => Integration::CATEGORY_AI,
            'is_enabled' => true, 'credentials' => ['api_key' => 'bad-key'],
        ]);

        $result = (new ClaudeTestDriver($integration))->test();

        $this->assertFalse($result['successful']);
        $this->assertSame('unauthorized', $result['error']);
    }

    /**
     * The driver deliberately no longer tests by listing models: a listing
     * succeeds even when the configured model has been retired, which is
     * exactly how this integration reported "connected" while every real
     * request failed. It now performs a tiny real generation, so the fake
     * mirrors an Interactions API response.
     */
    public function test_gemini_driver_succeeds_when_a_generation_returns_200(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [
                    ['content' => [['type' => 'text', 'text' => 'ok']]],
                ],
            ], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'gemini', 'category' => Integration::CATEGORY_AI,
            'is_enabled' => true, 'credentials' => ['api_key' => 'gem-key'],
        ]);

        $result = (new GeminiTestDriver($integration))->test();

        $this->assertTrue($result['successful']);
        $this->assertSame('ok', $result['response']['reply']);
        $this->assertSame(GeminiTestDriver::DEFAULT_MODEL, $result['response']['model']);

        // The key must travel as a header, not a query string — the older
        // `?key=` form is no longer accepted.
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'gem-key')
            && $request['model'] === GeminiTestDriver::DEFAULT_MODEL);
    }

    public function test_gemini_driver_uses_the_model_credential_when_one_is_set(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'steps' => [['content' => [['type' => 'text', 'text' => 'ok']]]],
            ], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'gemini', 'category' => Integration::CATEGORY_AI,
            'is_enabled' => true,
            'credentials' => ['api_key' => 'gem-key', 'model' => 'gemini-3.6-pro'],
        ]);

        $result = (new GeminiTestDriver($integration))->test();

        $this->assertTrue($result['successful']);
        $this->assertSame('gemini-3.6-pro', $result['response']['model']);
    }

    public function test_gemini_driver_fails_on_error_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'gemini', 'category' => Integration::CATEGORY_AI,
            'is_enabled' => true, 'credentials' => ['api_key' => 'bad'],
        ]);

        $result = (new GeminiTestDriver($integration))->test();

        $this->assertFalse($result['successful']);
        $this->assertSame('API key not valid', $result['error']);
    }

    public function test_google_maps_driver_succeeds_when_status_is_ok(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'OK', 'results' => []], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'google_maps', 'category' => Integration::CATEGORY_MAPS,
            'is_enabled' => true, 'credentials' => ['api_key' => 'maps-key'],
        ]);

        $result = (new GoogleMapsTestDriver($integration))->test();

        $this->assertTrue($result['successful']);
        $this->assertSame('OK', $result['response']['google_status']);
    }

    public function test_google_maps_driver_fails_when_status_is_not_ok(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'REQUEST_DENIED'], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'google_maps', 'category' => Integration::CATEGORY_MAPS,
            'is_enabled' => true, 'credentials' => ['api_key' => 'bad-key'],
        ]);

        $result = (new GoogleMapsTestDriver($integration))->test();

        $this->assertFalse($result['successful']);
        $this->assertSame('REQUEST_DENIED', $result['error']);
    }

    public function test_slack_driver_succeeds_when_ok_true(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response(['ok' => true, 'team' => 'Acme', 'user' => 'bot'], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'slack', 'category' => Integration::CATEGORY_COMMUNICATION,
            'is_enabled' => true, 'credentials' => ['bot_token' => 'xoxb-test'],
        ]);

        $result = (new SlackTestDriver($integration))->test();

        $this->assertTrue($result['successful']);
        $this->assertSame('Acme', $result['response']['team']);
    }

    public function test_slack_driver_fails_when_ok_false(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response(['ok' => false, 'error' => 'invalid_auth'], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'slack', 'category' => Integration::CATEGORY_COMMUNICATION,
            'is_enabled' => true, 'credentials' => ['bot_token' => 'bad-token'],
        ]);

        $result = (new SlackTestDriver($integration))->test();

        $this->assertFalse($result['successful']);
        $this->assertSame('invalid_auth', $result['error']);
    }

    public function test_generic_http_driver_succeeds_against_its_configured_test_endpoint(): void
    {
        Http::fake([
            'https://example.com/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'custom', 'category' => Integration::CATEGORY_CUSTOM,
            'is_enabled' => true,
            'credentials' => ['api_key' => 'generic-key', 'test_endpoint' => 'https://example.com/health'],
        ]);

        $result = (new GenericHttpTestDriver($integration))->test();

        $this->assertTrue($result['successful']);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/health'
            && $request->hasHeader('Authorization', 'Bearer generic-key'));
    }

    public function test_generic_http_driver_fails_without_a_test_endpoint_credential(): void
    {
        $integration = Integration::factory()->create([
            'provider' => 'custom', 'category' => Integration::CATEGORY_CUSTOM,
            'is_enabled' => true,
            'credentials' => ['api_key' => 'generic-key'],
        ]);

        $result = (new GenericHttpTestDriver($integration))->test();

        $this->assertFalse($result['successful']);
        $this->assertSame('No test_endpoint credential configured.', $result['error']);
    }

    public function test_generic_http_driver_fails_on_non_200_response(): void
    {
        Http::fake([
            'https://example.com/health' => Http::response(['error' => 'down'], 500),
        ]);

        $integration = Integration::factory()->create([
            'provider' => 'custom', 'category' => Integration::CATEGORY_CUSTOM,
            'is_enabled' => true,
            'credentials' => ['api_key' => 'generic-key', 'test_endpoint' => 'https://example.com/health'],
        ]);

        $result = (new GenericHttpTestDriver($integration))->test();

        $this->assertFalse($result['successful']);
        $this->assertSame('Connection test failed.', $result['error']);
    }

    public function test_driver_throws_when_integration_is_not_configured(): void
    {
        $integration = Integration::factory()->create([
            'provider' => 'slack', 'category' => Integration::CATEGORY_COMMUNICATION,
            'is_enabled' => false, 'credentials' => null,
        ]);

        $this->expectException(IntegrationNotConfiguredException::class);

        (new SlackTestDriver($integration))->test();
    }
}
