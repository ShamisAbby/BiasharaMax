<?php

namespace Tests\Unit\Integrations;

use App\Domain\Integrations\Drivers\ClaudeTestDriver;
use App\Domain\Integrations\Drivers\GeminiTestDriver;
use App\Domain\Integrations\Drivers\GenericHttpTestDriver;
use App\Domain\Integrations\Drivers\GoogleMapsTestDriver;
use App\Domain\Integrations\Drivers\OpenAiTestDriver;
use App\Domain\Integrations\Drivers\SlackTestDriver;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\IntegrationDriverResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntegrationDriverResolverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: class-string}>
     */
    public static function providerDriverMap(): array
    {
        return [
            'openai' => ['openai', OpenAiTestDriver::class],
            'claude' => ['claude', ClaudeTestDriver::class],
            'gemini' => ['gemini', GeminiTestDriver::class],
            'google_maps' => ['google_maps', GoogleMapsTestDriver::class],
            'slack' => ['slack', SlackTestDriver::class],
        ];
    }

    #[DataProvider('providerDriverMap')]
    public function test_resolves_the_correct_driver_for_each_provider(string $provider, string $expectedDriver): void
    {
        $integration = Integration::factory()->create(['provider' => $provider]);

        $driver = app(IntegrationDriverResolver::class)->resolve($integration);

        $this->assertInstanceOf($expectedDriver, $driver);
    }

    public function test_unknown_provider_falls_back_to_generic_http_driver(): void
    {
        $integration = Integration::factory()->create(['provider' => 'discord']);

        $driver = app(IntegrationDriverResolver::class)->resolve($integration);

        $this->assertInstanceOf(GenericHttpTestDriver::class, $driver);
    }
}
