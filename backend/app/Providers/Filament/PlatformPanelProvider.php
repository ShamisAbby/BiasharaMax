<?php

namespace App\Providers\Filament;

use App\Domain\Localization\Models\Currency;
use App\Domain\Platform\Filament\Pages\Dashboard;
use App\Domain\Platform\Filament\Pages\EditProfile;
use App\Domain\Platform\Filament\Resources\AuditLogs\AuditLogResource;
use App\Domain\Platform\Filament\Resources\BackupRecords\BackupRecordResource;
use App\Domain\Platform\Filament\Resources\Integrations\IntegrationResource;
use App\Domain\Platform\Filament\Resources\PlatformRoles\PlatformRoleResource;
use App\Domain\Platform\Services\PlatformStatusBadgeService;
use App\Http\Middleware\RedirectFilamentGuestsToPlatformLogin;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Mounted at /platform (not /admin) deliberately: routes/platform.php's
 * existing Inertia-rendered admin surface already owns the /admin prefix,
 * and both need to coexist while this rebuild happens one resource slice
 * at a time (see docs/ADR/0001-consolidation.md Phase 4). The final
 * cutover step of Phase 4 is renaming this panel's path to `admin` and
 * removing the old Inertia routes/pages together.
 *
 * Auth deliberately does NOT use Filament's own login/2FA — this app
 * already has a working platform.login flow with 2FA and signed staff
 * invitations (App\Domain\Platform\Http\Controllers\Auth\...). `->login()`
 * is intentionally not called (see RedirectFilamentGuestsToPlatformLogin's
 * docblock for why that matters), so this panel is purely gated behind the
 * existing `platform` guard/session — a user who's already logged in via
 * the existing flow can just navigate here.
 */
class PlatformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('platform')
            ->path('platform')
            ->authGuard('platform')
            // Matches the old /admin (Inertia/React) surface's palette
            // exactly — that app has no custom theme of its own, it's
            // plain unmodified Tailwind defaults, with `indigo-600`
            // (#4F46E5) used everywhere as the de facto brand color
            // (active nav state, links, primary buttons, logo, focus
            // rings — confirmed by reading its layout/sidebar/dashboard
            // components directly). Setting the other semantic colors
            // too so badges/alerts match /admin's BiBadge variants
            // (success=emerald, warning=amber, danger=red, info=blue)
            // panel-wide, not just on the dashboard.
            ->colors([
                'primary' => Color::Indigo,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'gray' => Color::Gray,
            ])
            // isSimple: false keeps the panel's sidebar/topbar chrome on
            // the profile page (the "simple" layout is meant for
            // standalone pre-auth-style pages, not one reached via the
            // account menu inside an already-authenticated panel).
            ->profile(EditProfile::class, isSimple: false)
            // Exact widths from /admin's own layout (PlatformLayout.tsx):
            // the expanded <aside> is `w-72` (18rem) and the collapsed
            // one is `w-20` (5rem). Filament's defaults are 20rem/4.5rem,
            // so both are set explicitly. Setting them through the panel
            // API rather than raw CSS keeps Filament's own
            // `--sidebar-width` / `--collapsed-sidebar-width` custom
            // properties correct, which the topbar offset in
            // dashboard.css reads from.
            ->sidebarWidth('18rem')
            ->collapsedSidebarWidth('5rem')
            // /admin has a "Collapse" toggle pinned to the bottom of the
            // sidebar; this enables the equivalent behavior, and the
            // SIDEBAR_FOOTER hook below supplies a button in the same
            // place (Filament's own toggle lives in the topbar, which is
            // hidden here — see dashboard.css's `.fi-topbar-start` rule).
            ->sidebarCollapsibleOnDesktop()
            // /admin's <main> is `flex-1 ... p-4 sm:p-6` — full width,
            // no max-width cap. Filament instead caps content at
            // `7xl` (80rem) and centers it, which on a wide screen
            // leaves large empty gutters either side of the dashboard
            // grid. Removing the cap here; dashboard.css trims the
            // padding to match /admin's p-4/sm:p-6.
            ->maxContentWidth(Width::Full)
            // Explicit rather than relying on config('app.name') (which
            // falls back to "Laravel" if APP_NAME isn't set) — matches
            // /admin's logo text exactly regardless of env config.
            ->brandName('BiasharaMax')
            // Matches /admin's real ⌘K search-everything behavior
            // (GlobalSearchController, confirmed searching businesses/
            // users/platform_users/subscriptions) — Filament's own
            // global search is on by default but has no keybinding
            // registered out of the box, so it's added explicitly here,
            // plus the visible ⌘K suffix in the search field itself.
            // Businesses and Staff are searchable via each resource's
            // $recordTitleAttribute (defaults getGloballySearchableAttributes()
            // to ['name']); Subscription-backed Subscribers isn't
            // included since Subscription has no meaningful title
            // attribute to search on.
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            // Real notification center: PlatformUser already uses
            // Laravel's Notifiable trait and a `notifications` table
            // migration already exists — enabling this is honest (an
            // empty-but-real bell, like /admin's own often-empty one),
            // not decorative. Nothing currently sends notifications to
            // platform users yet, so it'll show empty until something
            // does — that's an accurate reflection of current state.
            ->databaseNotifications()
            // "Activity" links to the real audit log, same destination
            // as /admin's user-menu "Activity" item.
            ->userMenuItems([
                Action::make('activity')
                    ->label('Activity')
                    ->icon(Heroicon::ChartBar)
                    ->url(fn (): string => AuditLogResource::getUrl()),
            ])
            // Without this, Filament orders sidebar groups by whatever
            // order resource discovery happens to return, which is
            // effectively alphabetical by directory name and puts
            // unrelated sections next to each other. Declaring them
            // fixes the order to run broad → narrow: who the customers
            // are, what they pay for, who runs the platform, then the
            // operational and configuration sections. Every group here
            // must match a resource's $navigationGroup string exactly —
            // a typo silently creates a second, separate group.
            ->navigationGroups([
                NavigationGroup::make('Tenants')->icon(Heroicon::OutlinedBuildingOffice2),
                NavigationGroup::make('Subscriptions')->icon(Heroicon::OutlinedCreditCard),
                NavigationGroup::make('Finance')->icon(Heroicon::OutlinedBanknotes),
                NavigationGroup::make('Administration')->icon(Heroicon::OutlinedIdentification),
                NavigationGroup::make('Support')->icon(Heroicon::OutlinedLifebuoy),
                NavigationGroup::make('Notifications')->icon(Heroicon::OutlinedBellAlert),
                NavigationGroup::make('Appearance')->icon(Heroicon::OutlinedSwatch),
                NavigationGroup::make('Configuration')->icon(Heroicon::OutlinedAdjustmentsHorizontal),
                NavigationGroup::make('Security')->icon(Heroicon::OutlinedShieldExclamation),
                NavigationGroup::make('System')->icon(Heroicon::OutlinedCog6Tooth),
            ])
            ->discoverResources(
                in: app_path('Domain/Platform/Filament/Resources'),
                for: 'App\\Domain\\Platform\\Filament\\Resources',
            )
            ->discoverPages(
                in: app_path('Domain/Platform/Filament/Pages'),
                for: 'App\\Domain\\Platform\\Filament\\Pages',
            )
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Domain/Platform/Filament/Widgets'),
                for: 'App\\Domain\\Platform\\Filament\\Widgets',
            )
            ->widgets([])
            // Custom dashboard widgets need layout/color CSS beyond what
            // Filament's own compiled stylesheet ships (that stylesheet
            // only contains its own `fi-*` component classes, not
            // arbitrary Tailwind utilities — confirmed by inspecting the
            // compiled output). A proper Filament theme needs Tailwind
            // v4, but this project's Vite pipeline is pinned to Tailwind
            // v3 for the existing Inertia/React /admin app, so upgrading
            // the shared `tailwindcss` package risks breaking that
            // build. Loading a small hand-authored, build-free stylesheet
            // via this render hook avoids touching the shared toolchain
            // entirely. See public/css/filament/platform/dashboard.css.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="stylesheet" href="'.e(asset('css/filament/platform/dashboard.css')).'">',
            )
            // "Admin" pill next to the brand name. Rendered in the
            // SIDEBAR header (not the topbar) — the sidebar already
            // carries its own "BiasharaMax" logo at all times now that it
            // spans full height, so the badge lives there too rather
            // than duplicating the brand in both places. The topbar's
            // own default logo slot (which Filament shows at >=1024px)
            // is hidden via CSS for the same reason — see
            // dashboard.css's `.fi-topbar-start` override.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn (): View => view('filament.platform.topbar.brand-badge'),
            )
            // "Search navigation…" box above the nav list, matching
            // /admin's BiSidebar — a real client-side filter over the
            // rendered nav items (same behavior as BiSidebar's, which
            // also filters the already-loaded item list rather than
            // querying the server).
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): View => view('filament.platform.sidebar.nav-search'),
            )
            // "← Collapse" button pinned to the sidebar's bottom edge,
            // same position and behavior as /admin's.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): View => view('filament.platform.sidebar.collapse-button'),
            )
            // Date display + status badge, matching /admin's topbar
            // layout — positioned right after the global search field,
            // before the notifications bell, same order as
            // PlatformLayout.tsx's equivalent elements.
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                function (): View {
                    return view('filament.platform.topbar.left-extras', $this->topbarHealthViewData());
                },
            )
            // Settings dropdown + currency + language + theme switcher,
            // positioned right before the user-menu avatar — i.e. right
            // *after* the native notifications bell, which can't be
            // reached via GLOBAL_SEARCH_AFTER since the bell itself is
            // hard-coded between that hook and USER_MENU_BEFORE in
            // Filament's own topbar.blade.php. This reproduces /admin's
            // full icon order: search → date → status → bell → gear →
            // currency → language → theme → avatar.
            //
            // Currency list is real, DB-backed (same Currency model
            // /admin's own switcher reads from, via the existing
            // CurrencyResource). Selecting one is a client-only display
            // preference (localStorage), same scope as /admin's own
            // switcher — it isn't threaded through every resource
            // table's amount columns in this rebuild yet. Language is a
            // simple two-option (English/Kiswahili) client preference
            // for the same reason /admin's own switcher is: it only
            // ever covered ~39 nav/chrome strings there, never real
            // backend i18n, so this matches that scope rather than
            // reimplementing something the original doesn't have either.
            // Theme switching reuses Filament's own real
            // <x-filament-panels::theme-switcher /> component rather
            // than a hand-rolled toggle, so persistence/behavior is
            // guaranteed correct.
            // Switch to the Inertia admin at /admin. Sits before the
            // settings/currency/language cluster so the two surfaces
            // present the control in the same relative position — an
            // admin who uses both should not have to hunt for it after
            // switching.
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.platform.topbar.surface-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function (): View {
                    return view('filament.platform.topbar.right-extras', [
                        'settingsLinks' => [
                            ['label' => 'Integrations', 'icon' => Heroicon::PuzzlePiece, 'url' => IntegrationResource::getUrl()],
                            ['label' => 'Backups', 'icon' => Heroicon::CircleStack, 'url' => BackupRecordResource::getUrl()],
                            ['label' => 'Roles & Permissions', 'icon' => Heroicon::ShieldCheck, 'url' => PlatformRoleResource::getUrl()],
                        ],
                        // `is_base` must be selected, not just ordered
                        // by: the view reads it to pick the default
                        // selection, and an unselected column is simply
                        // absent from the hydrated model (setRawAttributes()
                        // replaces the attribute array wholesale, so the
                        // model's own $attributes defaults don't fill it
                        // in either) — it would silently read as null.
                        // Cached because the topbar renders on every
                        // request and this list changes very rarely. The
                        // short TTL is the only invalidation — editing a
                        // currency takes up to 5 minutes to show up in
                        // this dropdown.
                        'currencies' => Cache::remember(
                            'platform.topbar.currencies',
                            300,
                            fn () => Currency::query()
                                ->where('is_active', true)
                                ->orderByDesc('is_base')
                                ->orderBy('code')
                                ->get(['code', 'name', 'symbol', 'is_base']),
                        ),
                        'languages' => [
                            ['code' => 'en', 'label' => 'English'],
                            ['code' => 'sw', 'label' => 'Kiswahili'],
                        ],
                    ]);
                },
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                RedirectFilamentGuestsToPlatformLogin::class,
            ]);
    }

    /**
     * Filament's Select renders a real `<select>` by default, which the
     * browser draws with its own OS widget — grey list, blue highlight,
     * a "Select an option" row, none of it themeable. Inside this dark
     * panel that looks like it belongs to a different application, and
     * only 14 of the 51 selects in the panel had opted out individually.
     *
     * Setting the default here rather than editing every call site means
     * new selects are consistent automatically. `configureUsing` runs
     * when a component is constructed, BEFORE its own chained calls, so
     * anything that genuinely wants the native control can still say
     * `->native(true)` and win.
     *
     * SelectFilter is configured separately: it composes a Select rather
     * than extending it, so it doesn't inherit the default.
     */
    public function boot(): void
    {
        Select::configureUsing(fn (Select $select): Select => $select->native(false));

        SelectFilter::configureUsing(fn (SelectFilter $filter): SelectFilter => $filter->native(false));
    }

    /**
     * Backs the topbar's date + status badge (see left-extras.blade.php).
     * 60s-cached since the topbar renders on every request — this is
     * real system health (database/Redis connectivity + the same
     * health-score label the dashboard uses), not /admin's hardcoded
     * always-"Operational" span.
     *
     * @return array{today: \Illuminate\Support\Carbon, statusColor: string, statusLabel: string, statusTitle: string}
     */
    private function topbarHealthViewData(): array
    {
        // Computation moved to PlatformStatusBadgeService so the Inertia
        // admin's top bar can show the same thing. It previously showed a
        // hardcoded "Operational" that could contradict this badge on the
        // same platform at the same moment.
        $status = app(PlatformStatusBadgeService::class)->current();

        return [
            'today' => now(),
            'statusColor' => $status['color'],
            'statusLabel' => $status['label'],
            'statusTitle' => $status['title'],
        ];
    }
}
