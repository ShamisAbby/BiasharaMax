<?php

namespace App\Domain\Platform\Filament\Resources\NotificationTemplates\Pages;

use App\Domain\Platform\Filament\Resources\NotificationTemplates\NotificationTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotificationTemplate extends CreateRecord
{
    protected static string $resource = NotificationTemplateResource::class;
}
