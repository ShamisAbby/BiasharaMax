<?php

namespace App\Domain\Platform\Filament\Resources\Integrations\RelationManagers;

use App\Domain\Integrations\Models\IntegrationLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only, mirrors IntegrationController::logs() — same field subset
 * that controller reshapes for the Inertia view (direction, event_type,
 * status_code, is_successful, error_message, created_at), same
 * latest-first order (defined on Integration::logs() itself). No create/
 * edit/delete: logs are written only by IntegrationService::testConnection()
 * and webhook delivery, never manually.
 */
class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => $state === IntegrationLog::DIRECTION_OUTBOUND ? 'info' : 'gray'),
                TextColumn::make('event_type'),
                TextColumn::make('status_code')->placeholder('—'),
                IconColumn::make('is_successful')->boolean()->label('Success'),
                TextColumn::make('error_message')->limit(60)->placeholder('—')->wrap(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
