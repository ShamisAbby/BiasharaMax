<?php

namespace App\Domain\Platform\Filament\Resources\TaxRates;

use App\Domain\Localization\Models\TaxRate;
use App\Domain\Platform\Filament\Resources\TaxRates\Pages\CreateTaxRate;
use App\Domain\Platform\Filament\Resources\TaxRates\Pages\EditTaxRate;
use App\Domain\Platform\Filament\Resources\TaxRates\Pages\ListTaxRates;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('country_code')->maxLength(2)->disabledOn('edit'),
            TextInput::make('rate')->required()->numeric()->minValue(0)->maxValue(100),
            Toggle::make('is_default'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('country_code')->placeholder('Global'),
                TextColumn::make('rate')->suffix('%'),
                IconColumn::make('is_default')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxRates::route('/'),
            'create' => CreateTaxRate::route('/create'),
            'edit' => EditTaxRate::route('/{record}/edit'),
        ];
    }
}
