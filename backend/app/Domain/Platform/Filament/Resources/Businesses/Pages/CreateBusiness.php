<?php

namespace App\Domain\Platform\Filament\Resources\Businesses\Pages;

use App\Domain\Platform\Filament\Resources\Businesses\BusinessResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;
}
