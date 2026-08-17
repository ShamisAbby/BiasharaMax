<?php

namespace App\Domain\Platform\Filament\Resources\Modules\Pages;

use App\Domain\Platform\Filament\Resources\Modules\ModuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModule extends CreateRecord
{
    protected static string $resource = ModuleResource::class;
}
