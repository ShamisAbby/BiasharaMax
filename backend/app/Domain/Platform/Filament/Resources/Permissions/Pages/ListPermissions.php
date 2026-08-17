<?php

namespace App\Domain\Platform\Filament\Resources\Permissions\Pages;

use App\Domain\Platform\Filament\Resources\Permissions\PermissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
