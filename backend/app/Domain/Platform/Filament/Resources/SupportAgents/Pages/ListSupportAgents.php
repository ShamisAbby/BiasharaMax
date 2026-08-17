<?php

namespace App\Domain\Platform\Filament\Resources\SupportAgents\Pages;

use App\Domain\Platform\Filament\Resources\SupportAgents\SupportAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupportAgents extends ListRecords
{
    protected static string $resource = SupportAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
