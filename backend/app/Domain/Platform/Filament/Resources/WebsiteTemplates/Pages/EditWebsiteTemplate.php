<?php

namespace App\Domain\Platform\Filament\Resources\WebsiteTemplates\Pages;

use App\Domain\Platform\Filament\Resources\WebsiteTemplates\WebsiteTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditWebsiteTemplate extends EditRecord
{
    protected static string $resource = WebsiteTemplateResource::class;
}
