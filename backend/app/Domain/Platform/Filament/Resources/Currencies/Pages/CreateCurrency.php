<?php

namespace App\Domain\Platform\Filament\Resources\Currencies\Pages;

use App\Domain\Platform\Filament\Resources\Currencies\CurrencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCurrency extends CreateRecord
{
    protected static string $resource = CurrencyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['rate_updated_at'] = now();

        return $data;
    }
}
