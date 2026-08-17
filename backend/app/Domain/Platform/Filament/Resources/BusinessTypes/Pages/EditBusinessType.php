<?php

namespace App\Domain\Platform\Filament\Resources\BusinessTypes\Pages;

use App\Domain\Platform\Filament\Resources\BusinessTypes\BusinessTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditBusinessType extends EditRecord
{
    protected static string $resource = BusinessTypeResource::class;
}
