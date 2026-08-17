<?php

namespace App\Domain\Platform\Filament\Resources\RoleTemplates\Schemas;

use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\RoleTemplate;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Field set mirrors App\Domain\Platform\Http\Requests\RoleTemplateRequest
 * — note the permission list is scoped to whichever `scope` (tenant vs
 * platform) is currently selected, matching the request's dynamic
 * `Rule::exists(...)->where('scope', $this->input('scope'))` validation.
 */
class RoleTemplateForm
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
                        ->unique(table: RoleTemplate::class, ignoreRecord: true),
                    Select::make('scope')
                        ->options(['tenant' => 'Tenant', 'platform' => 'Platform'])
                        ->required()
                        ->live(),
                    Textarea::make('description')->columnSpanFull(),
                ]),

            Section::make('Permissions')
                ->schema([
                    CheckboxList::make('permissions')
                        ->relationship('permissions', 'name', fn ($query, Get $get) => $query->when($get('scope'), fn ($q, $scope) => $q->where('scope', $scope)))
                        ->options(fn (Get $get) => Permission::query()
                            ->when($get('scope'), fn ($q, $scope) => $q->where('scope', $scope))
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
