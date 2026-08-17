<?php

namespace App\Domain\Platform\Filament\Resources\SupportDepartments\Pages;

use App\Domain\Platform\Filament\Resources\SupportDepartments\SupportDepartmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportDepartment extends CreateRecord
{
    protected static string $resource = SupportDepartmentResource::class;
}
