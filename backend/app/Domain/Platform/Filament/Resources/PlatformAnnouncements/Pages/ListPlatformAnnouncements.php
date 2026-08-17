<?php

namespace App\Domain\Platform\Filament\Resources\PlatformAnnouncements\Pages;

use App\Domain\Platform\Filament\Resources\PlatformAnnouncements\PlatformAnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAnnouncements extends ListRecords
{
    protected static string $resource = PlatformAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
