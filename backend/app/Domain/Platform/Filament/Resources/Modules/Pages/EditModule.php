<?php

namespace App\Domain\Platform\Filament\Resources\Modules\Pages;

use App\Domain\Platform\Filament\Resources\Modules\ModuleResource;
use Filament\Resources\Pages\EditRecord;

class EditModule extends EditRecord
{
    protected static string $resource = ModuleResource::class;
}
