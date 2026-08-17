<?php

namespace App\Domain\Platform\Filament\Resources\TaxRates\Pages;

use App\Domain\Platform\Filament\Resources\TaxRates\TaxRateResource;
use Filament\Resources\Pages\EditRecord;

class EditTaxRate extends EditRecord
{
    protected static string $resource = TaxRateResource::class;
}
