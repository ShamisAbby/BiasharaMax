<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformPulseService;
use Filament\Widgets\Widget;

/**
 * Renders the "AI Recommendations" section of the old Business Pulse
 * card as its own widget. Data is the same `ai_recommendations` /
 * `ai_configured` slice of PlatformPulseService::businessPulse() the
 * Inertia dashboard uses — when no AI-category Integration is enabled,
 * shows the same empty-state CTA pointing at Integrations rather than
 * fabricating recommendations.
 */
class AiRecommendationsWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.ai-recommendations';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $pulse = app(PlatformPulseService::class)->businessPulse();

        return [
            'recommendations' => $pulse['ai_recommendations'],
            'aiConfigured' => $pulse['ai_configured'],
        ];
    }
}
