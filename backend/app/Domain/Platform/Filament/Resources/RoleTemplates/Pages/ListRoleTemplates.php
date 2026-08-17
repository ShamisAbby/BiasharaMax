<?php

namespace App\Domain\Platform\Filament\Resources\RoleTemplates\Pages;

use App\Domain\Platform\Filament\Resources\RoleTemplates\RoleTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoleTemplates extends ListRecords
{
    protected static string $resource = RoleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
