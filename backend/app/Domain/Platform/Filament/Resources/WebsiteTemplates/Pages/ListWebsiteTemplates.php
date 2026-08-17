<?php

namespace App\Domain\Platform\Filament\Resources\WebsiteTemplates\Pages;

use App\Domain\Platform\Filament\Resources\WebsiteTemplates\WebsiteTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteTemplates extends ListRecords
{
    protected static string $resource = WebsiteTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
