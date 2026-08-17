<?php

namespace App\Domain\Platform\Filament\Resources\PaymentGateways;

use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Platform\Filament\Resources\PaymentGateways\Pages\CreatePaymentGateway;
use App\Domain\Platform\Filament\Resources\PaymentGateways\Pages\EditPaymentGateway;
use App\Domain\Platform\Filament\Resources\PaymentGateways\Pages\ListPaymentGateways;
use App\Domain\Platform\Filament\Resources\PaymentGateways\Schemas\PaymentGatewayForm;
use App\Domain\Platform\Filament\Resources\PaymentGateways\Tables\PaymentGatewaysTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentGatewayResource extends Resource
{
    protected static ?string $model = PaymentGateway::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Gateways';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('transactions');
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentGatewayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentGatewaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentGateways::route('/'),
            'create' => CreatePaymentGateway::route('/create'),
            'edit' => EditPaymentGateway::route('/{record}/edit'),
        ];
    }
}
