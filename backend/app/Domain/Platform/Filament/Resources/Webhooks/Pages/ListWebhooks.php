<?php

namespace App\Domain\Platform\Filament\Resources\Webhooks\Pages;

use App\Domain\Platform\Filament\Resources\Webhooks\WebhookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebhooks extends ListRecords
{
    protected static string $resource = WebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
