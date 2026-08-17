<?php

namespace App\Domain\Platform\Filament\Resources\Currencies\Pages;

use App\Domain\Platform\Filament\Resources\Currencies\CurrencyResource;
use Filament\Resources\Pages\EditRecord;

class EditCurrency extends EditRecord
{
    protected static string $resource = CurrencyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['rate_updated_at'] = now();

        return $data;
    }
}
