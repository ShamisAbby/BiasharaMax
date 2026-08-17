<?php

namespace App\Domain\Platform\Filament\Resources\Businesses\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('business_type')
                    ->required(),
                Select::make('business_type_id')
                    ->relationship('businessType', 'name'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->default(null),
                TextInput::make('country')
                    ->required()
                    ->default('TZ'),
                TextInput::make('currency')
                    ->required()
                    ->default('TZS'),
                TextInput::make('timezone')
                    ->required()
                    ->default('Africa/Dar_es_Salaam'),
                TextInput::make('address')
                    ->default(null),
                TextInput::make('city')
                    ->default(null),
                TextInput::make('logo_path')
                    ->default(null),
                Select::make('owner_id')
                    ->relationship('owner', 'name')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('trial'),
                DateTimePicker::make('trial_ends_at'),
                Textarea::make('settings')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->default(null),
                TextInput::make('updated_by')
                    ->default(null),
                TextInput::make('deleted_by')
                    ->default(null),
            ]);
    }
}
