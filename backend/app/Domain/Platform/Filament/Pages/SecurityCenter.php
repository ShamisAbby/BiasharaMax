<?php

namespace App\Domain\Platform\Filament\Pages;

use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Models\BlockedIp;
use App\Domain\Security\Models\FailedLoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Services\ActiveSessionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Custom page, not a Resource — mirrors
 * App\Domain\Platform\Http\Controllers\SecurityDashboardController.
 *
 * This panel already has Resources for Blocked IPs, Account Lockouts,
 * Security Alerts and Audit Logs individually. This page is not a
 * replacement for them; it is the overview that answers "is anything
 * happening right now", with the four lists side by side and the two
 * actions an admin reaches for in that moment — block an IP, resolve an
 * alert. Anything beyond triage still belongs in the Resources.
 *
 * Read and write permissions are separated the same way the Inertia
 * routes separate them: the page is visible with `security.view`, but
 * both actions additionally require `security.manage`.
 */
class SecurityCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Security Center';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Security Center';

    protected string $view = 'filament.platform.pages.security-center';

    public static function canAccess(): bool
    {
        return Auth::guard('platform')->user()?->hasPlatformPermission('security.view') ?? false;
    }

    public static function canManage(): bool
    {
        return Auth::guard('platform')->user()?->hasPlatformPermission('security.manage') ?? false;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('blockIp')
                ->label('Block an IP')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->visible(fn (): bool => static::canManage())
                ->schema([
                    TextInput::make('ip_address')
                        ->label('IP address')
                        ->required()
                        // Accepts v4 and v6. Left as validation rather than
                        // a mask because an admin pasting an address from a
                        // log should not have to reformat it.
                        ->rule('ip')
                        ->unique(BlockedIp::class, 'ip_address'),
                    TextInput::make('reason')
                        ->label('Reason')
                        // 500 to match BlockedIpController's rule exactly;
                        // a shorter cap here would reject text the other
                        // surface accepts.
                        ->maxLength(500)
                        ->helperText('Recorded on the block so the next admin knows why.'),
                    Toggle::make('is_permanent')
                        ->label('Permanent')
                        ->default(false)
                        ->live(),
                    DateTimePicker::make('expires_at')
                        ->label('Expires at')
                        ->after('now')
                        // Only meaningful for a temporary block, and
                        // required for one — a temporary block with no
                        // expiry is silently permanent, which is the
                        // opposite of what was asked for.
                        ->visible(fn ($get): bool => ! $get('is_permanent'))
                        ->required(fn ($get): bool => ! $get('is_permanent')),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canManage(), 403);

                    BlockedIp::query()->create([
                        'ip_address' => $data['ip_address'],
                        'reason' => $data['reason'] ?? null,
                        'is_permanent' => (bool) ($data['is_permanent'] ?? false),
                        'expires_at' => ($data['is_permanent'] ?? false) ? null : ($data['expires_at'] ?? null),
                        'blocked_by' => Auth::guard('platform')->id(),
                    ]);

                    Notification::make()
                        ->title('IP blocked')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function resolveAlert(string $alertId): void
    {
        abort_unless(static::canManage(), 403);

        $alert = SecurityAlert::query()->findOrFail($alertId);

        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => Auth::guard('platform')->id(),
        ]);

        Notification::make()->title('Alert resolved')->success()->send();
    }

    public function unblockIp(string $blockedIpId): void
    {
        abort_unless(static::canManage(), 403);

        BlockedIp::query()->findOrFail($blockedIpId)->delete();

        Notification::make()->title('IP unblocked')->success()->send();
    }

    public function unlockAccount(string $lockoutId): void
    {
        abort_unless(static::canManage(), 403);

        $lockout = AccountLockout::query()->findOrFail($lockoutId);

        $lockout->update([
            'unlocked_at' => now(),
            'unlocked_by' => Auth::guard('platform')->id(),
        ]);

        Notification::make()->title('Account unlocked')->success()->send();
    }

    /**
     * Per-request memo.
     *
     * The view renders each list once and the summary counts three of
     * them again, so without this every page load runs those queries
     * twice. Not a Livewire `#[Computed]` or a public property on
     * purpose — Eloquent collections in component state get serialised
     * into every subsequent request, and this page carries up to 300
     * rows.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * @template T
     *
     * @param  \Closure(): T  $resolve
     * @return T
     */
    private function once(string $key, \Closure $resolve): mixed
    {
        return $this->memo[$key] ??= $resolve();
    }

    /** @return Collection<int, SecurityAlert> */
    public function getAlerts(): Collection
    {
        return $this->once('alerts', fn () => SecurityAlert::query()->latest('created_at')->limit(100)->get());
    }

    /** @return Collection<int, BlockedIp> */
    public function getBlockedIps(): Collection
    {
        return $this->once('blockedIps', fn () => BlockedIp::query()->latest('created_at')->get());
    }

    /** @return Collection<int, AccountLockout> */
    public function getLockouts(): Collection
    {
        return $this->once('lockouts', fn () => AccountLockout::query()->latest('locked_at')->limit(50)->get());
    }

    /** @return Collection<int, FailedLoginAttempt> */
    public function getFailedLogins(): Collection
    {
        return $this->once('failedLogins', fn () => FailedLoginAttempt::query()->latest('created_at')->limit(50)->get());
    }

    /**
     * Sessions active in the last 30 minutes.
     *
     * Delegated to ActiveSessionService so this page and the Inertia
     * Security Centre give the same answer — including the distinction
     * between "nobody is signed in" and "this session driver cannot be
     * queried", which the two used to disagree about.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getActiveSessions(): Collection
    {
        return $this->once('activeSessions', fn () => app(ActiveSessionService::class)->recent());
    }

    public function sessionsAreTracked(): bool
    {
        return app(ActiveSessionService::class)->areTracked();
    }

    /**
     * @return array<string, int>
     */
    public function getSummary(): array
    {
        return [
            'failed_logins_24h' => FailedLoginAttempt::query()->where('created_at', '>=', now()->subDay())->count(),
            'blocked_ips_count' => $this->getBlockedIps()->filter(fn (BlockedIp $ip): bool => $ip->isActive())->count(),
            'active_lockouts_count' => $this->getLockouts()->filter(fn (AccountLockout $l): bool => $l->isActive())->count(),
            'unresolved_alerts_count' => SecurityAlert::query()->where('is_resolved', false)->count(),
            'active_sessions_count' => $this->getActiveSessions()->count(),
        ];
    }
}
