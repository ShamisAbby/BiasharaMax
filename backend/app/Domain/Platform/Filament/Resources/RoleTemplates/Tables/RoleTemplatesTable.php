<?php

namespace App\Domain\Platform\Filament\Resources\RoleTemplates\Tables;

use App\Domain\RBAC\Models\RoleTemplate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\RoleTemplateController:
 * delete is blocked for system templates, same message as the
 * controller's guard.
 */
class RoleTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('scope')
                    ->badge(),
                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions'),
            ])
            ->filters([
                SelectFilter::make('scope')
                    ->options(['tenant' => 'Tenant', 'platform' => 'Platform']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, RoleTemplate $record) {
                        if ($record->is_system) {
                            Notification::make()->title('System templates cannot be deleted.')->danger()->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }
}
