<?php

namespace App\Domain\Platform\Filament\Resources\RoleTemplates\Pages;

use App\Domain\Platform\Filament\Resources\RoleTemplates\RoleTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditRoleTemplate extends EditRecord
{
    protected static string $resource = RoleTemplateResource::class;
}
