<?php

namespace App\Domain\Platform\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('business_id')
                    ->relationship('business', 'name'),
                TextInput::make('module')
                    ->default(null),
                TextInput::make('actor_type')
                    ->default(null),
                TextInput::make('actor_id')
                    ->default(null),
                TextInput::make('action')
                    ->required(),
                TextInput::make('auditable_type')
                    ->default(null),
                TextInput::make('auditable_id')
                    ->default(null),
                Textarea::make('old_values')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('new_values')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('ip_address')
                    ->default(null),
                TextInput::make('user_agent')
                    ->default(null),
                TextInput::make('browser')
                    ->default(null),
                TextInput::make('operating_system')
                    ->default(null),
                TextInput::make('device_type')
                    ->default(null),
                TextInput::make('country')
                    ->default(null),
                TextInput::make('risk_level')
                    ->required()
                    ->default('normal'),
            ]);
    }
}
