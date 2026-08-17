<?php

namespace App\Domain\Platform\Filament\Resources\AuditLogs\Tables;

use App\Domain\Shared\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only, matching AuditLogController::index — platform staff browse
 * and search, nothing here is ever created or edited by hand. Filters
 * cover the same fields the old page's `filters` query params did
 * (action, actor_type, module, risk_level); Filament's own column
 * ->searchable() replaces the old free-text `search` param (which only
 * matched auditable_type/business name — this searches more columns,
 * a reasonable superset).
 */
class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('module')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('actor_type')
                    ->label('Actor'),
                TextColumn::make('auditable_type')
                    ->label('Record type')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)
                    ->searchable(),
                TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('ip_address')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('risk_level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AuditLog::RISK_HIGH => 'danger',
                        AuditLog::RISK_ELEVATED => 'warning',
                        AuditLog::RISK_LOW => 'gray',
                        default => 'info',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('actor_type')
                    ->options([
                        'user' => 'User',
                        'platform_user' => 'Platform user',
                    ]),
                SelectFilter::make('risk_level')
                    ->options([
                        AuditLog::RISK_LOW => 'Low',
                        AuditLog::RISK_NORMAL => 'Normal',
                        AuditLog::RISK_ELEVATED => 'Elevated',
                        AuditLog::RISK_HIGH => 'High',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
