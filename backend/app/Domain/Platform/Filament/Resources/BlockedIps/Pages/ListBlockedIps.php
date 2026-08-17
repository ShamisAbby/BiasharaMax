<?php

namespace App\Domain\Platform\Filament\Resources\BlockedIps\Pages;

use App\Domain\Platform\Filament\Resources\BlockedIps\BlockedIpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlockedIps extends ListRecords
{
    protected static string $resource = BlockedIpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Block IP'),
        ];
    }
}
