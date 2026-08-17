<?php

namespace App\Domain\Platform\Filament\Pages;

use App\Domain\Developer\Models\Webhook;
use App\Domain\Developer\Models\WebhookDelivery;
use App\Domain\Developer\Services\DeveloperToolsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Custom page, not a Resource — mirrors
 * App\Domain\Platform\Http\Controllers\DeveloperCenterController.
 *
 * Everything reported here comes from DeveloperToolsService, which is
 * also what the old /admin screen calls. That service is where the
 * redaction rules live, so routing every read through it is what keeps
 * credentials off this page.
 *
 * Webhooks have a full Resource in this panel already (under System).
 * They are listed here read-only, as context next to the API tokens and
 * queue state, with a link across for anything beyond looking.
 */
class DeveloperCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Developer Center';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Developer Center';

    protected string $view = 'filament.platform.pages.developer-center';

    /**
     * Free-text filter over the route list. Public so Livewire binds it;
     * `$routeFilter` is a plain string, which is safe to keep in
     * component state — unlike the route list itself.
     */
    public string $routeFilter = '';

    /**
     * A freshly created token, shown once.
     *
     * Sanctum hashes the token on save, so this value cannot be recovered
     * after this request — if the admin navigates away without copying
     * it, the only remedy is to revoke and issue another. That is why it
     * gets a dismissible banner rather than a toast that slides away on a
     * timer.
     *
     * `#[Locked]` because it round-trips in the component payload until
     * dismissed: locked means the browser can echo it back but cannot
     * substitute a different value, so nothing downstream can be tricked
     * into displaying an attacker-chosen string as "your new token".
     */
    #[Locked]
    public ?string $plainTextToken = null;

    /**
     * Which webhook's delivery log is expanded, if any. Inline rather
     * than a separate screen — the question "did it deliver" is asked
     * while looking at the list, not somewhere else.
     */
    public ?string $expandedWebhookId = null;

    public static function canAccess(): bool
    {
        return Auth::guard('platform')->user()?->hasPlatformPermission('developer.view') ?? false;
    }

    public static function canManage(): bool
    {
        return Auth::guard('platform')->user()?->hasPlatformPermission('developer.manage') ?? false;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearCache')
                ->label('Clear cache')
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->visible(fn (): bool => static::canManage())
                // Cache::flush() empties the whole store, and on this
                // installation the cache, session and queue all share
                // Redis. Worth a confirmation that says so rather than a
                // generic "are you sure".
                ->requiresConfirmation()
                ->modalHeading('Clear the application cache?')
                ->modalDescription('Every cached value is dropped, including anything the app is currently relying on. The next requests will be slower while it repopulates.')
                ->action(function (): void {
                    abort_unless(static::canManage(), 403);

                    app(DeveloperToolsService::class)->clearCache();

                    Notification::make()->title('Cache cleared')->success()->send();
                }),

            Action::make('newToken')
                ->label('New token')
                ->icon(Heroicon::OutlinedKey)
                ->visible(fn (): bool => static::canManage())
                ->schema([
                    TextInput::make('name')
                        ->label('Token name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('What this token is for. It is the only way to tell two tokens apart later.'),
                    TagsInput::make('abilities')
                        ->label('Abilities')
                        ->placeholder('Add an ability')
                        // Sanctum treats an empty ability list as "no
                        // permissions", not "all" — so the default has to
                        // be an explicit wildcard, matching
                        // ApiTokenController's `?? ['*']`.
                        ->default(['*'])
                        ->helperText('Leave as * for full access.'),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canManage(), 403);

                    $user = Auth::guard('platform')->user();
                    abort_unless($user !== null, 403);

                    $token = $user->createToken(
                        $data['name'],
                        ! empty($data['abilities']) ? $data['abilities'] : ['*'],
                    );

                    $this->plainTextToken = $token->plainTextToken;

                    Notification::make()
                        ->title('Token created')
                        ->body('Copy it now — it cannot be shown again.')
                        ->warning()
                        ->send();
                }),

            Action::make('newWebhook')
                ->label('New webhook')
                ->icon(Heroicon::OutlinedBolt)
                ->visible(fn (): bool => static::canManage())
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('url')
                        ->label('Delivery URL')
                        ->required()
                        ->url()
                        ->maxLength(255),
                    TagsInput::make('events')
                        ->label('Events')
                        ->required()
                        ->placeholder('business.created')
                        // A tag input rather than the old screen's
                        // comma-separated text field. Same stored shape —
                        // `events` is an array cast — but a typo is
                        // visible as its own chip instead of hiding inside
                        // a string that silently never matches.
                        ->helperText('One or more event names. A webhook with no events is never called.'),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canManage(), 403);

                    Webhook::query()->create([
                        'name' => $data['name'],
                        'url' => $data['url'],
                        'events' => $data['events'],
                        // Generated here, never asked for: it is the
                        // signing secret receivers verify against, so it
                        // must be unguessable rather than admin-chosen.
                        'secret' => Str::random(40),
                        'is_active' => true,
                        'created_by' => Auth::guard('platform')->id(),
                    ]);

                    Notification::make()->title('Webhook created')->success()->send();
                }),
        ];
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    public function revokeToken(string $tokenId): void
    {
        abort_unless(static::canManage(), 403);

        $user = Auth::guard('platform')->user();
        abort_unless($user !== null, 403);

        // Scoped to the signed-in admin's own tokens, exactly as
        // ApiTokenController does. Without the ownership filter this
        // would revoke any token on the platform by id.
        $user->tokens()->where('id', $tokenId)->delete();

        Notification::make()->title('Token revoked')->success()->send();
    }

    public function deleteWebhook(string $webhookId): void
    {
        abort_unless(static::canManage(), 403);

        Webhook::query()->findOrFail($webhookId)->delete();

        if ($this->expandedWebhookId === $webhookId) {
            $this->expandedWebhookId = null;
        }

        Notification::make()->title('Webhook deleted')->success()->send();
    }

    public function toggleDeliveries(string $webhookId): void
    {
        $this->expandedWebhookId = $this->expandedWebhookId === $webhookId ? null : $webhookId;
    }

    /**
     * Recent deliveries for the expanded webhook.
     *
     * `response_body` is deliberately not selected — it is a `text`
     * column holding whatever the receiver returned, which can be an
     * entire HTML error page. The status code is what answers "did it
     * work"; the body belongs in a detail view, not a list.
     *
     * @return Collection<int, WebhookDelivery>
     */
    public function getDeliveries(): Collection
    {
        if ($this->expandedWebhookId === null) {
            return collect();
        }

        return WebhookDelivery::query()
            ->where('webhook_id', $this->expandedWebhookId)
            ->latest('created_at')
            ->limit(25)
            ->get(['id', 'event', 'response_status', 'is_successful', 'attempt', 'delivered_at', 'created_at']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSystemInfo(): array
    {
        return app(DeveloperToolsService::class)->systemInfo();
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueueStatus(): array
    {
        return app(DeveloperToolsService::class)->queueStatus();
    }

    /**
     * Migration counts rather than the full list.
     *
     * The service returns one row per migration file — 188 on this
     * installation — and the screen only ever showed "x / y ran". Sending
     * all of them to the browser to render two numbers is the kind of
     * thing that makes an admin page feel slow for no visible reason.
     *
     * @return array{total: int, ran: int, pending: array<int, string>}
     */
    public function getMigrationStatus(): array
    {
        $migrations = collect(app(DeveloperToolsService::class)->migrationStatus());

        return [
            'total' => $migrations->count(),
            'ran' => $migrations->where('ran', true)->count(),
            // Named, because "3 pending" without saying which three is
            // the moment you go and run `migrate:status` in a terminal
            // anyway.
            'pending' => $migrations->where('ran', false)->pluck('migration')->values()->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRoutes(): array
    {
        $routes = collect(app(DeveloperToolsService::class)->routeList());

        if ($this->routeFilter !== '') {
            $needle = mb_strtolower(trim($this->routeFilter));

            $routes = $routes->filter(
                fn (array $route): bool => str_contains(mb_strtolower($route['uri']), $needle)
                    || str_contains(mb_strtolower((string) $route['name']), $needle),
            );
        }

        // Capped. The unfiltered list is well over a thousand rows, and a
        // table that long is slower to render than it is to scroll — the
        // filter above is the intended way through it, so the cap nudges
        // toward using it instead of hiding that anything was omitted.
        return $routes->take(200)->values()->all();
    }

    public function getRouteCount(): int
    {
        return count(app(DeveloperToolsService::class)->routeList());
    }

    /**
     * @return Collection<int, Webhook>
     */
    public function getWebhooks(): Collection
    {
        return Webhook::query()->withCount('deliveries')->get();
    }

    /**
     * The signed-in admin's own tokens only, matching the old screen —
     * `$request->user()->tokens()`. Never every token on the platform.
     *
     * @return Collection<int, \Laravel\Sanctum\PersonalAccessToken>
     */
    public function getApiTokens(): Collection
    {
        $user = Auth::guard('platform')->user();

        if (! $user || ! method_exists($user, 'tokens')) {
            return collect();
        }

        return $user->tokens()->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']);
    }
}
