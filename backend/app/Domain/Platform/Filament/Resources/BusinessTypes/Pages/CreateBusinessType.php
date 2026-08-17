<?php

namespace App\Domain\Platform\Filament\Resources\BusinessTypes\Pages;

use App\Domain\Platform\Filament\Resources\BusinessTypes\BusinessTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessType extends CreateRecord
{
    protected static string $resource = BusinessTypeResource::class;
}
