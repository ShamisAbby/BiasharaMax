<?php

namespace App\Domain\Platform\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('business.name')
                    ->label('Business')
                    ->placeholder('-'),
                TextEntry::make('module')
                    ->placeholder('-'),
                TextEntry::make('actor_type')
                    ->placeholder('-'),
                TextEntry::make('actor_id')
                    ->placeholder('-'),
                TextEntry::make('action'),
                TextEntry::make('auditable_type')
                    ->placeholder('-'),
                TextEntry::make('auditable_id')
                    ->placeholder('-'),
                TextEntry::make('old_values')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?array $state): ?string => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                    ->columnSpanFull(),
                TextEntry::make('new_values')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?array $state): ?string => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                    ->columnSpanFull(),
                TextEntry::make('ip_address')
                    ->placeholder('-'),
                TextEntry::make('user_agent')
                    ->placeholder('-'),
                TextEntry::make('browser')
                    ->placeholder('-'),
                TextEntry::make('operating_system')
                    ->placeholder('-'),
                TextEntry::make('device_type')
                    ->placeholder('-'),
                TextEntry::make('country')
                    ->placeholder('-'),
                TextEntry::make('risk_level'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
