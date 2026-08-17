<?php

namespace App\Domain\Platform\Filament\Resources\WebsiteTemplates\Pages;

use App\Domain\Platform\Filament\Resources\WebsiteTemplates\WebsiteTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWebsiteTemplate extends CreateRecord
{
    protected static string $resource = WebsiteTemplateResource::class;
}
