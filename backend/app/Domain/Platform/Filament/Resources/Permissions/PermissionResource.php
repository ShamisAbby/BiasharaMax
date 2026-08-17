<?php

namespace App\Domain\Platform\Filament\Resources\Permissions;

use App\Domain\Platform\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Domain\Platform\Filament\Resources\Permissions\Tables\PermissionsTable;
use App\Domain\RBAC\Models\Permission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Permission Matrix';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return PermissionsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
        ];
    }
}
