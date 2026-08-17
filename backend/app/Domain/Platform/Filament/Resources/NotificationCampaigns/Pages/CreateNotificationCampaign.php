<?php

namespace App\Domain\Platform\Filament\Resources\NotificationCampaigns\Pages;

use App\Domain\Platform\Filament\Resources\NotificationCampaigns\NotificationCampaignResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateNotificationCampaign extends CreateRecord
{
    protected static string $resource = NotificationCampaignResource::class;

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
