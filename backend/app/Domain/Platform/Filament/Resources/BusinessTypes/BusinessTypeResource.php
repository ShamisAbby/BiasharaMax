<?php

namespace App\Domain\Platform\Filament\Resources\BusinessTypes;

use App\Domain\Business\Models\BusinessType;
use App\Domain\Platform\Filament\Resources\BusinessTypes\Pages\CreateBusinessType;
use App\Domain\Platform\Filament\Resources\BusinessTypes\Pages\EditBusinessType;
use App\Domain\Platform\Filament\Resources\BusinessTypes\Pages\ListBusinessTypes;
use App\Domain\Platform\Filament\Resources\BusinessTypes\Schemas\BusinessTypeForm;
use App\Domain\Platform\Filament\Resources\BusinessTypes\Tables\BusinessTypesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BusinessTypeResource extends Resource
{
    protected static ?string $model = BusinessType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('businesses');
    }

    public static function form(Schema $schema): Schema
    {
        return BusinessTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessTypes::route('/'),
            'create' => CreateBusinessType::route('/create'),
            'edit' => EditBusinessType::route('/{record}/edit'),
        ];
    }
}
