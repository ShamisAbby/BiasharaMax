<?php

namespace App\Domain\Platform\Filament\Resources\BusinessTypes\Tables;

use App\Domain\Business\Exceptions\BusinessTypeInUseException;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Business\Services\BusinessTypeService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\BusinessTypeController /
 * BusinessTypeService: delete is blocked (with the service's own
 * exception message) when any business uses the type, matching the
 * controller's try/catch around BusinessTypeInUseException instead of
 * letting a raw FK error surface.
 */
class BusinessTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ColorColumn::make('color')
                    ->label(''),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        BusinessType::STATUS_ACTIVE => 'success',
                        BusinessType::STATUS_INACTIVE => 'gray',
                        BusinessType::STATUS_ARCHIVED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('businesses_count')
                    ->label('Businesses')
                    ->counts('businesses'),
                TextColumn::make('default_currency')
                    ->placeholder('—'),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        BusinessType::STATUS_ACTIVE => 'Active',
                        BusinessType::STATUS_INACTIVE => 'Inactive',
                        BusinessType::STATUS_ARCHIVED => 'Archived',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    static::cloneAction(),
                    static::activateAction(),
                    static::deactivateAction(),
                    static::archiveAction(),
                ]),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, BusinessType $record, BusinessTypeService $service) {
                        try {
                            $service->delete($record);
                            $action->cancel();

                            Notification::make()->title('Business type deleted.')->success()->send();
                        } catch (BusinessTypeInUseException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    protected static function cloneAction(): Action
    {
        return Action::make('clone')
            ->icon(Heroicon::DocumentDuplicate)
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
            ])
            ->action(function (BusinessType $record, array $data, BusinessTypeService $service): void {
                $service->clone($record, $data['name']);

                Notification::make()->title('Business type cloned.')->success()->send();
            });
    }

    protected static function activateAction(): Action
    {
        return Action::make('activate')
            ->color('success')
            ->icon(Heroicon::CheckCircle)
            ->visible(fn (BusinessType $record): bool => $record->status !== BusinessType::STATUS_ACTIVE)
            ->action(function (BusinessType $record, BusinessTypeService $service): void {
                $service->activate($record);
            });
    }

    protected static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->color('gray')
            ->icon(Heroicon::MinusCircle)
            ->visible(fn (BusinessType $record): bool => $record->status === BusinessType::STATUS_ACTIVE)
            ->action(function (BusinessType $record, BusinessTypeService $service): void {
                $service->deactivate($record);
            });
    }

    protected static function archiveAction(): Action
    {
        return Action::make('archive')
            ->color('danger')
            ->icon(Heroicon::ArchiveBox)
            ->requiresConfirmation()
            ->visible(fn (BusinessType $record): bool => $record->status !== BusinessType::STATUS_ARCHIVED)
            ->action(function (BusinessType $record, BusinessTypeService $service): void {
                $service->archive($record);
            });
    }
}
