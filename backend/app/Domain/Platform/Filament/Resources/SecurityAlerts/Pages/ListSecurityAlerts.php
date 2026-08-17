<?php

namespace App\Domain\Platform\Filament\Resources\SecurityAlerts\Pages;

use App\Domain\Platform\Filament\Resources\SecurityAlerts\SecurityAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListSecurityAlerts extends ListRecords
{
    protected static string $resource = SecurityAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
