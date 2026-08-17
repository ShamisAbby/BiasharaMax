<?php

namespace App\Domain\Platform\Filament\Resources\SupportDepartments\Pages;

use App\Domain\Platform\Filament\Resources\SupportDepartments\SupportDepartmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupportDepartments extends ListRecords
{
    protected static string $resource = SupportDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
