<?php

namespace App\Domain\Platform\Filament\Resources\PlatformAnnouncements\Pages;

use App\Domain\Platform\Filament\Resources\PlatformAnnouncements\PlatformAnnouncementResource;
use Filament\Resources\Pages\EditRecord;

class EditPlatformAnnouncement extends EditRecord
{
    protected static string $resource = PlatformAnnouncementResource::class;
}
