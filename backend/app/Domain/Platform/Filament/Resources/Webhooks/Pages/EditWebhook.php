<?php

namespace App\Domain\Platform\Filament\Resources\Webhooks\Pages;

use App\Domain\Platform\Filament\Resources\Webhooks\WebhookResource;
use Filament\Resources\Pages\EditRecord;

class EditWebhook extends EditRecord
{
    protected static string $resource = WebhookResource::class;
}
