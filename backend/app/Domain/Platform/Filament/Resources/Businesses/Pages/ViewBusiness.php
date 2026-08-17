<?php

namespace App\Domain\Platform\Filament\Resources\Businesses\Pages;

use App\Domain\Platform\Filament\Resources\Businesses\BusinessResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBusiness extends ViewRecord
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
