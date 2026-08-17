<?php

namespace App\Domain\Platform\Filament\Resources\Licenses\Tables;

use App\Domain\Business\Models\Business;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Services\LicenseService;
use App\Domain\Licensing\Services\OfflineCertificateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\LicenseController and the
 * App\Domain\Licensing\Services\LicenseService methods it delegates to.
 * No edit page — the old UI has no update route either, only the
 * specific lifecycle actions below.
 */
class LicensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('license_key')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        License::STATUS_ACTIVE => 'success',
                        License::STATUS_SUSPENDED => 'warning',
                        License::STATUS_REVOKED, License::STATUS_EXPIRED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('active_devices_count')
                    ->label('Devices')
                    ->counts('activeDevices')
                    ->formatStateUsing(fn (License $record, $state): string => "{$state} / {$record->max_devices}"),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('maintenance_expires_at')
                    ->label('Maintenance until')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        License::STATUS_ACTIVE => 'Active',
                        License::STATUS_SUSPENDED => 'Suspended',
                        License::STATUS_REVOKED => 'Revoked',
                        License::STATUS_EXPIRED => 'Expired',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        License::TYPE_STARTER => 'Starter',
                        License::TYPE_PROFESSIONAL => 'Professional',
                        License::TYPE_ENTERPRISE => 'Enterprise',
                        License::TYPE_LIFETIME => 'Lifetime',
                    ]),
            ])
            ->headerActions([
                static::generateAction(),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::renewAction(),
                    static::resetActivationAction(),
                    static::downloadCertificateAction(),
                ])->label('Manage')->icon(Heroicon::Cog6Tooth),

                static::suspendAction(),
                static::restoreAction(),
                static::revokeAction(),
            ]);
    }

    protected static function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Generate license')
            ->icon(Heroicon::Plus)
            ->schema([
                Select::make('business_id')
                    ->label('Business')
                    ->options(fn () => Business::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('type')
                    ->options([
                        License::TYPE_STARTER => 'Starter',
                        License::TYPE_PROFESSIONAL => 'Professional',
                        License::TYPE_ENTERPRISE => 'Enterprise',
                        License::TYPE_LIFETIME => 'Lifetime',
                    ])
                    ->required(),
                TextInput::make('max_devices')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->required(),
                DateTimePicker::make('expires_at')
                    ->native(false)
                    ->minDate(now()->addDay()),
                DateTimePicker::make('maintenance_expires_at')
                    ->native(false),
                Toggle::make('offline_activation_allowed')->default(true),
                Toggle::make('cloud_sync_enabled'),
                Textarea::make('notes')->columnSpanFull(),
            ])
            ->action(function (array $data, LicenseService $licenses): void {
                $licenses->generate([
                    'business_id' => $data['business_id'],
                    'type' => $data['type'],
                    'max_devices' => $data['max_devices'],
                    'expires_at' => $data['expires_at'] ? Carbon::parse($data['expires_at']) : null,
                    'maintenance_expires_at' => $data['maintenance_expires_at'] ? Carbon::parse($data['maintenance_expires_at']) : null,
                    'offline_activation_allowed' => $data['offline_activation_allowed'] ?? true,
                    'cloud_sync_enabled' => $data['cloud_sync_enabled'] ?? false,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => Auth::guard('platform')->id(),
                ]);

                Notification::make()->title('License generated.')->success()->send();
            });
    }

    protected static function renewAction(): Action
    {
        return Action::make('renew')
            ->icon(Heroicon::ArrowPath)
            ->schema([
                DateTimePicker::make('expires_at')->native(false),
                DateTimePicker::make('maintenance_expires_at')->native(false),
            ])
            ->fillForm(fn (License $record): array => [
                'expires_at' => $record->expires_at,
                'maintenance_expires_at' => $record->maintenance_expires_at,
            ])
            ->action(function (License $record, array $data, LicenseService $licenses): void {
                $licenses->renew(
                    $record,
                    $data['expires_at'] ? Carbon::parse($data['expires_at']) : null,
                    $data['maintenance_expires_at'] ? Carbon::parse($data['maintenance_expires_at']) : null,
                );

                Notification::make()->title('License renewed.')->success()->send();
            });
    }

    protected static function resetActivationAction(): Action
    {
        return Action::make('resetActivation')
            ->label('Reset activation')
            ->icon(Heroicon::ArrowUturnLeft)
            ->requiresConfirmation()
            ->modalDescription('This deactivates every currently-active device on this license.')
            ->action(function (License $record, LicenseService $licenses): void {
                $licenses->resetActivation($record);

                Notification::make()->title('Activation reset.')->success()->send();
            });
    }

    protected static function downloadCertificateAction(): Action
    {
        return Action::make('downloadCertificate')
            ->label('Download certificate')
            ->icon(Heroicon::ArrowDownTray)
            ->action(function (License $record, OfflineCertificateService $certificates) {
                $certificate = $certificates->generateCertificate($record);

                return response()->streamDownload(
                    fn () => print ($certificate),
                    "{$record->license_key}.lic",
                );
            });
    }

    protected static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->color('warning')
            ->icon(Heroicon::PauseCircle)
            ->requiresConfirmation()
            ->visible(fn (License $record): bool => $record->status !== License::STATUS_SUSPENDED)
            ->action(function (License $record, LicenseService $licenses): void {
                $licenses->suspend($record);

                Notification::make()->title('License suspended.')->success()->send();
            });
    }

    protected static function restoreAction(): Action
    {
        return Action::make('restore')
            ->color('success')
            ->icon(Heroicon::PlayCircle)
            ->visible(fn (License $record): bool => in_array($record->status, [License::STATUS_SUSPENDED, License::STATUS_EXPIRED], true))
            ->action(function (License $record, LicenseService $licenses): void {
                $licenses->restore($record);

                Notification::make()->title('License restored.')->success()->send();
            });
    }

    protected static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->color('danger')
            ->icon(Heroicon::XCircle)
            ->visible(fn (License $record): bool => $record->status !== License::STATUS_REVOKED)
            ->schema([
                Textarea::make('reason')->required()->maxLength(255),
            ])
            ->action(function (License $record, array $data, LicenseService $licenses): void {
                $licenses->revoke($record, $data['reason']);

                Notification::make()->title('License revoked.')->success()->send();
            });
    }
}
