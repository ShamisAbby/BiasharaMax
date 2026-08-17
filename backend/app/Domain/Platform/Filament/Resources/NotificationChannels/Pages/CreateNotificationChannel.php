<?php

namespace App\Domain\Platform\Filament\Resources\NotificationChannels\Pages;

use App\Domain\Platform\Filament\Resources\NotificationChannels\NotificationChannelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotificationChannel extends CreateRecord
{
    protected static string $resource = NotificationChannelResource::class;
}
