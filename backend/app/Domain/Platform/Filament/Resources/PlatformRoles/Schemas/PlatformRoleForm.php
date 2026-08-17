<?php

namespace App\Domain\Platform\Filament\Resources\PlatformRoles\Schemas;

use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\PlatformRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Field set mirrors App\Domain\Platform\Http\Requests\PlatformRoleRequest. */
class PlatformRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', str($state)->slug()) : null),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(table: PlatformRole::class, ignoreRecord: true),
                    Textarea::make('description')->columnSpanFull(),
                ]),

            Section::make('Permissions')
                ->schema([
                    CheckboxList::make('permissions')
                        ->relationship('permissions', 'name', fn ($query) => $query->where('scope', Permission::SCOPE_PLATFORM))
                        ->options(fn () => Permission::query()
                            ->where('scope', Permission::SCOPE_PLATFORM)
                            ->orderBy('module')
                            ->orderBy('action')
                            ->get()
                            ->mapWithKeys(fn (Permission $permission) => [$permission->id => "{$permission->module}: {$permission->name}"]))
                        ->columns(2)
                        ->searchable(),
                ]),
        ]);
    }
}
