<?php

namespace App\Domain\Platform\Filament\Resources\RegistrationCodes\Pages;

use App\Domain\Platform\Filament\Resources\RegistrationCodes\RegistrationCodeResource;
use Filament\Resources\Pages\ListRecords;

class ListRegistrationCodes extends ListRecords
{
    protected static string $resource = RegistrationCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
