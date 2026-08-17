<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformPulseService;
use Filament\Widgets\Widget;

/**
 * Mirrors the old dashboard's "Live Activity" feed — same
 * PlatformPulseService::liveActivity(12) call (actor/business name
 * resolution lives entirely in that service, reused as-is rather than
 * reimplemented against the AuditLog model directly).
 */
class LiveActivityWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.live-activity';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 2;

    protected function getViewData(): array
    {
        return [
            'activity' => app(PlatformPulseService::class)->liveActivity(12),
        ];
    }
}
