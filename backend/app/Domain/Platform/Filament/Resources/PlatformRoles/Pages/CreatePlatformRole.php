<?php

namespace App\Domain\Platform\Filament\Resources\PlatformRoles\Pages;

use App\Domain\Platform\Filament\Resources\PlatformRoles\PlatformRoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformRole extends CreateRecord
{
    protected static string $resource = PlatformRoleResource::class;
}
