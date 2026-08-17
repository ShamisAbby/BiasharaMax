<?php

namespace App\Domain\Platform\Filament\Resources\PlatformUsers\Pages;

use App\Domain\Platform\Filament\Resources\PlatformUsers\PlatformUserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPlatformUser extends ViewRecord
{
    protected static string $resource = PlatformUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
