<?php

namespace App\Domain\Platform\Filament\Resources\Licenses\Pages;

use App\Domain\Platform\Filament\Resources\Licenses\LicenseResource;
use Filament\Resources\Pages\ListRecords;

class ListLicenses extends ListRecords
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
