<?php

namespace App\Domain\Platform\Filament\Resources\Currencies;

use App\Domain\Localization\Models\Currency;
use App\Domain\Platform\Filament\Resources\Currencies\Pages\CreateCurrency;
use App\Domain\Platform\Filament\Resources\Currencies\Pages\EditCurrency;
use App\Domain\Platform\Filament\Resources\Currencies\Pages\ListCurrencies;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * No delete route — matches PlatformSettingsController exactly (only
 * storeCurrency/updateCurrency exist). Editing an existing currency only
 * exposes exchange_rate_to_base/is_active, matching updateCurrency()'s
 * validation (code/name/symbol are create-only, hence disabled on edit).
 */
class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(3)->unique(table: Currency::class, ignoreRecord: true)->disabledOn('edit'),
            TextInput::make('name')->required()->maxLength(255)->disabledOn('edit'),
            TextInput::make('symbol')->required()->maxLength(10)->disabledOn('edit'),
            TextInput::make('exchange_rate_to_base')->required()->numeric()->minValue(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name'),
                TextColumn::make('symbol'),
                TextColumn::make('exchange_rate_to_base')->label('Rate to base')->numeric(4),
                IconColumn::make('is_base')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurrencies::route('/'),
            'create' => CreateCurrency::route('/create'),
            'edit' => EditCurrency::route('/{record}/edit'),
        ];
    }
}
