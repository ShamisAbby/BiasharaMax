<?php

namespace App\Domain\Platform\Filament\Resources\BlockedIps\Pages;

use App\Domain\Platform\Filament\Resources\BlockedIps\BlockedIpResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBlockedIp extends CreateRecord
{
    protected static string $resource = BlockedIpResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['blocked_by'] = Auth::guard('platform')->id();

        return $data;
    }
}
