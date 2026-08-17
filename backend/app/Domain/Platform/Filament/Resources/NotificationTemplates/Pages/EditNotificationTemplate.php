<?php

namespace App\Domain\Platform\Filament\Resources\NotificationTemplates\Pages;

use App\Domain\Platform\Filament\Resources\NotificationTemplates\NotificationTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditNotificationTemplate extends EditRecord
{
    protected static string $resource = NotificationTemplateResource::class;
}
