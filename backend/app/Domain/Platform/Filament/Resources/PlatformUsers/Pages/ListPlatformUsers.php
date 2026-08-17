<?php

namespace App\Domain\Platform\Filament\Resources\PlatformUsers\Pages;

use App\Domain\Platform\Filament\Resources\PlatformUsers\PlatformUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformUsers extends ListRecords
{
    protected static string $resource = PlatformUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
