<?php

namespace App\Domain\Platform\Filament\Resources\Integrations\Pages;

use App\Domain\Platform\Filament\Resources\Integrations\IntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntegrations extends ListRecords
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
