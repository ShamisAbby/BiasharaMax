<?php

namespace App\Domain\Platform\Filament\Resources\Integrations\Pages;

use App\Domain\Platform\Filament\Resources\Integrations\IntegrationResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Replicates IntegrationController::update()'s credentials merge exactly:
 * blank/null submitted credential values are treated as "leave unchanged"
 * rather than overwriting the stored (encrypted) value with blank — same
 * pattern used for PaymentGateway and NotificationChannel credentials.
 */
class EditIntegration extends EditRecord
{
    protected static string $resource = IntegrationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('credentials', $data)) {
            $nonBlank = array_filter($data['credentials'] ?? [], fn ($value) => $value !== '' && $value !== null);
            $data['credentials'] = $nonBlank === []
                ? $this->record->credentials
                : array_merge($this->record->credentials ?? [], $nonBlank);
        }

        return $data;
    }
}
