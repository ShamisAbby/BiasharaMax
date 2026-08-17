<?php

namespace App\Domain\Platform\Filament\Resources\SubscriptionTransactions\Pages;

use App\Domain\Platform\Filament\Resources\SubscriptionTransactions\SubscriptionTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionTransactions extends ListRecords
{
    protected static string $resource = SubscriptionTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
