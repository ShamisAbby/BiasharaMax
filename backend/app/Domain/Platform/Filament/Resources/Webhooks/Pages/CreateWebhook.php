<?php

namespace App\Domain\Platform\Filament\Resources\Webhooks\Pages;

use App\Domain\Platform\Filament\Resources\Webhooks\WebhookResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateWebhook extends CreateRecord
{
    protected static string $resource = WebhookResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['secret'] = Str::random(40);
        $data['created_by'] = Auth::guard('platform')->id();

        return $data;
    }
}
