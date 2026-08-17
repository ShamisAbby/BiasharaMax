<?php

namespace App\Domain\Platform\Filament\Resources\PlatformRoles\Pages;

use App\Domain\Platform\Filament\Resources\PlatformRoles\PlatformRoleResource;
use Filament\Resources\Pages\EditRecord;

class EditPlatformRole extends EditRecord
{
    protected static string $resource = PlatformRoleResource::class;
}
