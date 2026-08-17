<?php

namespace App\Domain\Platform\Filament\Resources\AccountLockouts\Pages;

use App\Domain\Platform\Filament\Resources\AccountLockouts\AccountLockoutResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountLockout extends CreateRecord
{
    protected static string $resource = AccountLockoutResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['locked_at'] = now();

        return $data;
    }
}
