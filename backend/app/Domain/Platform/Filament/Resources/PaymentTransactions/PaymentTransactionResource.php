<?php

namespace App\Domain\Platform\Filament\Resources\PaymentTransactions;

use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Platform\Filament\Resources\PaymentTransactions\Pages\ListPaymentTransactions;
use App\Domain\Platform\Filament\Resources\PaymentTransactions\Tables\PaymentTransactionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * No edit page — payment transactions are recorded (manually or by a
 * gateway) then only acted on via retry/approve/refund, matching
 * App\Domain\Platform\Http\Controllers\PaymentTransactionController
 * (no update route).
 */
class PaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function table(Table $table): Table
    {
        return PaymentTransactionsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentTransactions::route('/'),
        ];
    }
}
