<?php

namespace App\Domain\Platform\Filament\Resources\Modules;

use App\Domain\ModuleManagement\Models\Module;
use App\Domain\Platform\Filament\Resources\Modules\Pages\CreateModule;
use App\Domain\Platform\Filament\Resources\Modules\Pages\EditModule;
use App\Domain\Platform\Filament\Resources\Modules\Pages\ListModules;
use App\Domain\Platform\Filament\Resources\Modules\Schemas\ModuleForm;
use App\Domain\Platform\Filament\Resources\Modules\Tables\ModulesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('businesses');
    }

    public static function form(Schema $schema): Schema
    {
        return ModuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModules::route('/'),
            'create' => CreateModule::route('/create'),
            'edit' => EditModule::route('/{record}/edit'),
        ];
    }
}
