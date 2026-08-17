<?php

namespace App\Domain\Platform\Filament\Resources\Businesses\Schemas;

use App\Domain\Business\Models\Business;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BusinessInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('business_type'),
                TextEntry::make('businessType.name')
                    ->label('Business type')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('country'),
                TextEntry::make('currency'),
                TextEntry::make('timezone'),
                TextEntry::make('address')
                    ->placeholder('-'),
                TextEntry::make('city')
                    ->placeholder('-'),
                TextEntry::make('logo_path')
                    ->placeholder('-'),
                TextEntry::make('owner.name')
                    ->label('Owner'),
                TextEntry::make('status'),
                TextEntry::make('trial_ends_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('settings')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_by')
                    ->placeholder('-'),
                TextEntry::make('updated_by')
                    ->placeholder('-'),
                TextEntry::make('deleted_by')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Business $record): bool => $record->trashed()),
            ]);
    }
}
