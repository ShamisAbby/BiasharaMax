<?php

namespace App\Domain\Platform\Filament\Resources\RoleTemplates;

use App\Domain\Platform\Filament\Resources\RoleTemplates\Pages\CreateRoleTemplate;
use App\Domain\Platform\Filament\Resources\RoleTemplates\Pages\EditRoleTemplate;
use App\Domain\Platform\Filament\Resources\RoleTemplates\Pages\ListRoleTemplates;
use App\Domain\Platform\Filament\Resources\RoleTemplates\Schemas\RoleTemplateForm;
use App\Domain\Platform\Filament\Resources\RoleTemplates\Tables\RoleTemplatesTable;
use App\Domain\RBAC\Models\RoleTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoleTemplateResource extends Resource
{
    protected static ?string $model = RoleTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Role Templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('permissions');
    }

    public static function form(Schema $schema): Schema
    {
        return RoleTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoleTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoleTemplates::route('/'),
            'create' => CreateRoleTemplate::route('/create'),
            'edit' => EditRoleTemplate::route('/{record}/edit'),
        ];
    }
}
