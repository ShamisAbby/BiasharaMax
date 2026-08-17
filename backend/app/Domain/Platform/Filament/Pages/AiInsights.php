<?php

namespace App\Domain\Platform\Filament\Pages;

use App\Domain\AiInsights\Models\AiInsight;
use App\Domain\AiInsights\Services\AiNarrativeService;
use App\Domain\AiInsights\Services\InsightGenerationService;
use App\Domain\Integrations\Models\Integration;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Custom page, not a Resource — mirrors
 * App\Domain\Platform\Http\Controllers\AiInsightController exactly.
 * All forecast/analytics numbers are computed directly from
 * InsightGenerationService (real statistical methods, no ML model — see
 * that class's docblock). Narrative generation delegates to
 * AiNarrativeService::summarize(), which itself returns null (and this
 * page then shows a danger notification, matching the controller's
 * validation-error flash) when no AI-category Integration is enabled and
 * configured. The `aiConfigured` banner flag replicates the controller's
 * inline check exactly (category=ai + is_enabled only — deliberately NOT
 * the stricter Integration::isConfigured()/credentials check that
 * AiNarrativeService itself uses internally, matching the original UI's
 * slightly looser badge).
 */
class AiInsights extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'AI Insights';

    protected static ?string $title = 'AI Insights';

    protected string $view = 'filament.platform.pages.ai-insights';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'type' => AiInsight::TYPE_REVENUE_FORECAST,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Generate narrative for')
                    ->options([
                        AiInsight::TYPE_REVENUE_FORECAST => 'Revenue forecast',
                        AiInsight::TYPE_CHURN_RISK => 'Churn risk',
                        AiInsight::TYPE_GROWTH_TREND => 'Growth trend',
                    ])
                    ->required(),
            ])
            ->statePath('data');
    }

    /**
     * @return array<string, mixed>
     */
    public function getRevenueForecastProperty(): array
    {
        return app(InsightGenerationService::class)->revenueForecast();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionForecastProperty(): array
    {
        return app(InsightGenerationService::class)->subscriptionForecast();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getChurnRiskProperty(): array
    {
        return app(InsightGenerationService::class)->churnRisk();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBusinessHealthScoresProperty(): array
    {
        return app(InsightGenerationService::class)->businessHealthScores();
    }

    /**
     * @return array<string, mixed>
     */
    public function getGrowthTrendProperty(): array
    {
        return app(InsightGenerationService::class)->growthTrend();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMostActiveBusinessesProperty(): array
    {
        return app(InsightGenerationService::class)->mostActiveBusinesses();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInactiveBusinessesProperty(): array
    {
        return app(InsightGenerationService::class)->inactiveBusinesses();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRevenueByBusinessTypeProperty(): array
    {
        return app(InsightGenerationService::class)->revenueByBusinessType();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRevenueByCountryProperty(): array
    {
        return app(InsightGenerationService::class)->revenueByCountry();
    }

    public function getSavedInsightsProperty(): Collection
    {
        return AiInsight::query()->latest('created_at')->limit(20)->get();
    }

    public function getAiConfiguredProperty(): bool
    {
        return Integration::query()
            ->where('category', Integration::CATEGORY_AI)
            ->where('is_enabled', true)
            ->exists();
    }

    public function generateNarrative(InsightGenerationService $insights, AiNarrativeService $narrative): void
    {
        $type = $this->form->getState()['type'] ?? null;

        $data = match ($type) {
            AiInsight::TYPE_REVENUE_FORECAST => $insights->revenueForecast(),
            AiInsight::TYPE_CHURN_RISK => $insights->churnRisk(),
            AiInsight::TYPE_GROWTH_TREND => $insights->growthTrend(),
            default => [],
        };

        $summary = $narrative->summarize($type, $data);

        if ($summary === null) {
            // The service's own reason. This previously always claimed
            // no provider was configured, which contradicted the
            // integration card sitting one screen away showing Enabled,
            // Configured and a successful last test.
            Notification::make()
                ->title('Could not generate a summary')
                ->body($narrative->lastError() ?? 'The AI provider did not return a summary.')
                ->danger()
                ->send();

            return;
        }

        AiInsight::query()->create([
            'type' => $type,
            'title' => ucwords(str_replace('_', ' ', $type)),
            'summary' => $summary,
            'data' => $data,
            'generated_by' => AiInsight::GENERATED_BY_AI_PROVIDER,
        ]);

        Notification::make()->title('Narrative generated.')->success()->send();

        unset($this->savedInsights);
    }

    public function markInsightRead(string $insightId): void
    {
        AiInsight::query()->whereKey($insightId)->update(['is_read' => true]);

        unset($this->savedInsights);

        Notification::make()->title('Marked as read.')->success()->send();
    }
}
