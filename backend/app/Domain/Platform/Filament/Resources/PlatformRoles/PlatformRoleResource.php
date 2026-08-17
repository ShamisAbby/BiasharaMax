<?php

namespace App\Domain\Platform\Filament\Resources\PlatformRoles;

use App\Domain\Platform\Filament\Resources\PlatformRoles\Pages\CreatePlatformRole;
use App\Domain\Platform\Filament\Resources\PlatformRoles\Pages\EditPlatformRole;
use App\Domain\Platform\Filament\Resources\PlatformRoles\Pages\ListPlatformRoles;
use App\Domain\Platform\Filament\Resources\PlatformRoles\Schemas\PlatformRoleForm;
use App\Domain\Platform\Filament\Resources\PlatformRoles\Tables\PlatformRolesTable;
use App\Domain\RBAC\Models\PlatformRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlatformRoleResource extends Resource
{
    protected static ?string $model = PlatformRole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Platform Roles';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['permissions', 'platformUsers']);
    }

    public static function form(Schema $schema): Schema
    {
        return PlatformRoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformRolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformRoles::route('/'),
            'create' => CreatePlatformRole::route('/create'),
            'edit' => EditPlatformRole::route('/{record}/edit'),
        ];
    }
}
