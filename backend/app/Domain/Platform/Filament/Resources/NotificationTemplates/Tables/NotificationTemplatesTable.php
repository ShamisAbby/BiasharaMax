<?php

namespace App\Domain\Platform\Filament\Resources\NotificationTemplates\Tables;

use App\Domain\Notifications\Models\NotificationTemplate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NotificationTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('channel')->badge(),
                TextColumn::make('category'),
                IconColumn::make('is_active')->boolean(),
                IconColumn::make('is_system')->label('System')->boolean(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options(fn () => NotificationTemplate::query()->distinct()->orderBy('channel')->pluck('channel', 'channel')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, NotificationTemplate $record) {
                        if ($record->is_system) {
                            Notification::make()->title('System templates cannot be deleted.')->danger()->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }
}
