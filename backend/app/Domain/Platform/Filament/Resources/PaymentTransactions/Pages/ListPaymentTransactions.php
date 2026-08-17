<?php

namespace App\Domain\Platform\Filament\Resources\PaymentTransactions\Pages;

use App\Domain\Platform\Filament\Resources\PaymentTransactions\PaymentTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentTransactions extends ListRecords
{
    protected static string $resource = PaymentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
