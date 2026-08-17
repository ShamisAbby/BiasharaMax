<?php

namespace App\Domain\Platform\Filament\Resources\SubscriptionTransactions;

use App\Domain\Platform\Filament\Resources\SubscriptionTransactions\Pages\ListSubscriptionTransactions;
use App\Domain\Platform\Filament\Resources\SubscriptionTransactions\Tables\SubscriptionTransactionsTable;
use App\Domain\Subscription\Models\SubscriptionTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only, matching SubscriptionTransactionController (index only — no
 * store/update/destroy routes exist). Transactions are only ever created
 * as a side effect of SubscriptionService::renewWithPayment(), called
 * from the Subscribers "record renewal payment" action.
 */
class SubscriptionTransactionResource extends Resource
{
    protected static ?string $model = SubscriptionTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?string $navigationLabel = 'Transactions';

    public static function table(Table $table): Table
    {
        return SubscriptionTransactionsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionTransactions::route('/'),
        ];
    }
}
