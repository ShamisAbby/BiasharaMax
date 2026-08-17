<?php

namespace App\Domain\Platform\Filament\Resources\NotificationChannels\Pages;

use App\Domain\Platform\Filament\Resources\NotificationChannels\NotificationChannelResource;
use Filament\Resources\Pages\EditRecord;

class EditNotificationChannel extends EditRecord
{
    protected static string $resource = NotificationChannelResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('credentials', $data)) {
            $nonBlank = array_filter(
                $data['credentials'] ?? [],
                fn ($value) => $value !== '' && $value !== null,
            );

            $data['credentials'] = $nonBlank === []
                ? $this->record->credentials
                : array_merge($this->record->credentials ?? [], $nonBlank);
        }

        return $data;
    }
}
