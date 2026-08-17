<?php

namespace App\Domain\Platform\Filament\Resources\BusinessTypes\Pages;

use App\Domain\Platform\Filament\Resources\BusinessTypes\BusinessTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBusinessTypes extends ListRecords
{
    protected static string $resource = BusinessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
