<?php

namespace Tests\Unit\AiInsights;

use App\Domain\AiInsights\Services\AiNarrativeService;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiNarrativeServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiNarrativeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AiNarrativeService::class);
    }

    public function test_summarize_returns_null_when_no_ai_integration_is_configured(): void
    {
        $summary = $this->service->summarize('Revenue Forecast', ['forecast_next_month' => 1000]);

        $this->assertNull($summary);
    }

    public function test_summarize_returns_null_when_an_ai_integration_exists_but_is_disabled(): void
    {
        Integration::factory()->create([
            'category' => Integration::CATEGORY_AI,
            'provider' => 'openai',
            'is_enabled' => false,
            'credentials' => ['api_key' => 'sk-test'],
        ]);

        $summary = $this->service->summarize('Revenue Forecast', ['forecast_next_month' => 1000]);

        $this->assertNull($summary);
    }

    public function test_summarize_returns_the_faked_completion_text_when_an_enabled_ai_integration_exists(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Revenue is forecast to grow steadily next month.']]],
            ], 200),
        ]);

        Integration::factory()->create([
            'category' => Integration::CATEGORY_AI,
            'provider' => 'openai',
            'is_enabled' => true,
            'credentials' => ['api_key' => 'sk-test'],
        ]);

        $summary = $this->service->summarize('Revenue Forecast', ['forecast_next_month' => 1000]);

        $this->assertSame('Revenue is forecast to grow steadily next month.', $summary);
    }

    public function test_summarize_returns_null_when_the_driver_completion_is_empty(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['choices' => []], 200),
        ]);

        Integration::factory()->create([
            'category' => Integration::CATEGORY_AI,
            'provider' => 'openai',
            'is_enabled' => true,
            'credentials' => ['api_key' => 'sk-test'],
        ]);

        $summary = $this->service->summarize('Revenue Forecast', ['forecast_next_month' => 1000]);

        $this->assertNull($summary);
    }
}
