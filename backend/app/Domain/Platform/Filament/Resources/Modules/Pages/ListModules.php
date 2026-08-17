<?php

namespace App\Domain\Platform\Filament\Resources\Modules\Pages;

use App\Domain\Platform\Filament\Resources\Modules\ModuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModules extends ListRecords
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
