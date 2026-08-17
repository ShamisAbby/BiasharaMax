<?php

namespace App\Domain\Platform\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Overrides the stock Filament dashboard purely for layout: a 3-column
 * grid so the paired "2/3 + 1/3" rows from the old Inertia dashboard
 * (Live Activity + Quick Actions, Revenue Trend + Payment Methods, etc.)
 * lay out the same way here via each widget's own $columnSpan. Widget
 * content/ordering is otherwise fully controlled by the widget classes
 * themselves (auto-discovered from Domain/Platform/Filament/Widgets,
 * sorted by their $sort property) — nothing else is overridden here.
 */
class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return 3;
    }

    /**
     * No page heading: DashboardHeaderWidget (sort 0) already opens the
     * page with a greeting, date and platform version, so Filament's own
     * "Dashboard" <h1> above it was a redundant second title. Returning
     * null makes Filament skip rendering the header block entirely
     * rather than leaving an empty, still-spaced one.
     */
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * Kept explicitly so dropping the heading doesn't also blank the
     * browser tab title and navigation label, which default to it.
     */
    public function getTitle(): string|Htmlable
    {
        return 'Dashboard';
    }
}
