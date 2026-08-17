<?php

namespace App\Domain\Platform\Filament\Resources\SecurityAlerts;

use App\Domain\Platform\Filament\Resources\SecurityAlerts\Pages\ListSecurityAlerts;
use App\Domain\Security\Models\SecurityAlert;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only + resolve — matches
 * App\Domain\Platform\Http\Controllers\SecurityAlertController (only a
 * resolve route, alerts are system-generated).
 */
class SecurityAlertResource extends Resource
{
    protected static ?string $model = SecurityAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Security Alerts';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SecurityAlert::SEVERITY_CRITICAL, SecurityAlert::SEVERITY_HIGH => 'danger',
                        SecurityAlert::SEVERITY_MEDIUM => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('description')->limit(60)->placeholder('—'),
                TextColumn::make('ip_address')->fontFamily('mono')->placeholder('—'),
                TextColumn::make('is_resolved')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Resolved' : 'Open')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options([
                        SecurityAlert::SEVERITY_LOW => 'Low',
                        SecurityAlert::SEVERITY_MEDIUM => 'Medium',
                        SecurityAlert::SEVERITY_HIGH => 'High',
                        SecurityAlert::SEVERITY_CRITICAL => 'Critical',
                    ]),
                TernaryFilter::make('is_resolved'),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->color('success')
                    ->icon(Heroicon::CheckCircle)
                    ->visible(fn (SecurityAlert $record): bool => ! $record->is_resolved)
                    ->action(function (SecurityAlert $record): void {
                        $record->update([
                            'is_resolved' => true,
                            'resolved_by' => Auth::guard('platform')->id(),
                            'resolved_at' => now(),
                        ]);
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSecurityAlerts::route('/'),
        ];
    }
}
