<?php

namespace App\Domain\Platform\Filament\Resources\TaxRates\Pages;

use App\Domain\Platform\Filament\Resources\TaxRates\TaxRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxRates extends ListRecords
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
