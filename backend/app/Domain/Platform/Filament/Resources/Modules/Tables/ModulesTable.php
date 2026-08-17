<?php

namespace App\Domain\Platform\Filament\Resources\Modules\Tables;

use App\Domain\ModuleManagement\Exceptions\ModuleException;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\ModuleManagement\Services\ModuleService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\ModuleController /
 * ModuleService: delete is blocked (service's own exception message)
 * when any business has the module, same guarded pattern as Business
 * Types.
 */
class ModulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version'),
                TextColumn::make('category')
                    ->placeholder('—'),
                TextColumn::make('visibility')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Module::STATUS_ACTIVE => 'success',
                        Module::STATUS_INACTIVE => 'gray',
                        Module::STATUS_DEPRECATED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('businesses_count')
                    ->label('Businesses')
                    ->counts('businesses'),
                TextColumn::make('is_premium')
                    ->label('Premium')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Module::STATUS_ACTIVE => 'Active',
                        Module::STATUS_INACTIVE => 'Inactive',
                        Module::STATUS_DEPRECATED => 'Deprecated',
                    ]),
                SelectFilter::make('visibility')
                    ->options([
                        Module::VISIBILITY_PUBLIC => 'Public',
                        Module::VISIBILITY_HIDDEN => 'Hidden',
                        Module::VISIBILITY_BETA => 'Beta',
                    ]),
                TernaryFilter::make('is_premium'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    static::enableAction(),
                    static::disableAction(),
                    static::updateVersionAction(),
                ]),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Module $record, ModuleService $service) {
                        try {
                            $service->delete($record);
                            $action->cancel();

                            Notification::make()->title('Module deleted.')->success()->send();
                        } catch (ModuleException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    protected static function enableAction(): Action
    {
        return Action::make('enable')
            ->color('success')
            ->icon(Heroicon::CheckCircle)
            ->visible(fn (Module $record): bool => $record->status !== Module::STATUS_ACTIVE)
            ->action(fn (Module $record, ModuleService $service) => $service->enable($record));
    }

    protected static function disableAction(): Action
    {
        return Action::make('disable')
            ->color('gray')
            ->icon(Heroicon::MinusCircle)
            ->visible(fn (Module $record): bool => $record->status === Module::STATUS_ACTIVE)
            ->action(fn (Module $record, ModuleService $service) => $service->disable($record));
    }

    protected static function updateVersionAction(): Action
    {
        return Action::make('updateVersion')
            ->label('Update version')
            ->icon(Heroicon::ArrowUpCircle)
            ->schema([
                TextInput::make('version')->required()->maxLength(20),
                Textarea::make('notes'),
            ])
            ->action(function (Module $record, array $data, ModuleService $service): void {
                $service->updateVersion($record, $data['version'], $data['notes'] ?? null, Auth::guard('platform')->id());

                Notification::make()->title('Version updated.')->success()->send();
            });
    }
}
