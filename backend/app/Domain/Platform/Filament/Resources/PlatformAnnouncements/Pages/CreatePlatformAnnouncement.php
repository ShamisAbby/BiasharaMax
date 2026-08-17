<?php

namespace App\Domain\Platform\Filament\Resources\PlatformAnnouncements\Pages;

use App\Domain\Platform\Filament\Resources\PlatformAnnouncements\PlatformAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePlatformAnnouncement extends CreateRecord
{
    protected static string $resource = PlatformAnnouncementResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::guard('platform')->id();

        return $data;
    }
}
