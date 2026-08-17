<?php

namespace App\Domain\Platform\Filament\Resources\NotificationCampaigns\Pages;

use App\Domain\Platform\Filament\Resources\NotificationCampaigns\NotificationCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationCampaigns extends ListRecords
{
    protected static string $resource = NotificationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
