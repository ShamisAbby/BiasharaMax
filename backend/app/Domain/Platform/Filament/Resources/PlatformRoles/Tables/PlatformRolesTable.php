<?php

namespace App\Domain\Platform\Filament\Resources\PlatformRoles\Tables;

use App\Domain\RBAC\Models\PlatformRole;
use App\Domain\RBAC\Models\RoleTemplate;
use App\Domain\RBAC\Services\RoleTemplateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\PlatformRoleController:
 * delete is blocked for system roles and for roles currently assigned to
 * any platform user, with the exact same messages, instead of a raw FK
 * error or silently deleting a role out from under active staff.
 */
class PlatformRolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions'),
                TextColumn::make('platform_users_count')
                    ->label('Staff assigned')
                    ->counts('platformUsers'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    static::cloneAction(),
                    static::applyTemplateAction(),
                ]),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, PlatformRole $record) {
                        if ($record->is_system) {
                            Notification::make()->title('System roles cannot be deleted.')->danger()->send();
                            $action->cancel();

                            return;
                        }

                        if ($record->platformUsers()->exists()) {
                            Notification::make()->title('This role is assigned to platform users and cannot be deleted.')->danger()->send();
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
            ->action(function (PlatformRole $record, array $data, RoleTemplateService $service): void {
                $service->clonePlatformRole($record, $data['name']);

                Notification::make()->title('Role cloned.')->success()->send();
            });
    }

    protected static function applyTemplateAction(): Action
    {
        return Action::make('applyTemplate')
            ->label('Apply template')
            ->icon(Heroicon::ClipboardDocumentList)
            ->schema([
                Select::make('role_template_id')
                    ->label('Template')
                    ->options(fn () => RoleTemplate::query()->where('scope', 'platform')->pluck('name', 'id'))
                    ->required(),
            ])
            ->action(function (PlatformRole $record, array $data, RoleTemplateService $service): void {
                $template = RoleTemplate::query()->findOrFail($data['role_template_id']);
                $service->applyToPlatformRole($record, $template);

                Notification::make()->title('Template applied.')->success()->send();
            });
    }
}
