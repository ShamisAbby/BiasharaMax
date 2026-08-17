<?php

namespace App\Domain\Platform\Filament\Resources\NotificationChannels\Tables;

use App\Domain\Notifications\Models\NotificationChannel;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotificationChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('channel')->badge(),
                TextColumn::make('provider'),
                IconColumn::make('is_enabled')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('enable')
                    ->color('success')
                    ->icon(Heroicon::CheckCircle)
                    ->visible(fn (NotificationChannel $record): bool => ! $record->is_enabled)
                    ->action(fn (NotificationChannel $record) => $record->update(['is_enabled' => true])),
                Action::make('disable')
                    ->color('gray')
                    ->icon(Heroicon::MinusCircle)
                    ->visible(fn (NotificationChannel $record): bool => $record->is_enabled)
                    ->action(fn (NotificationChannel $record) => $record->update(['is_enabled' => false])),
            ]);
    }
}
