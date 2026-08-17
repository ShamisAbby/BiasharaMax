<?php

namespace App\Domain\Platform\Filament\Resources\PaymentGateways\Pages;

use App\Domain\Platform\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Replicates PaymentGatewayController::update()'s credential-merge
 * behavior exactly: a blank value for a credential key means "leave it
 * unchanged" (so re-saving the form without re-entering a secret never
 * wipes it), merged into the existing stored set rather than overwritten
 * wholesale.
 */
class EditPaymentGateway extends EditRecord
{
    protected static string $resource = PaymentGatewayResource::class;

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
