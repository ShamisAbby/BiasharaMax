<?php

namespace App\Domain\Platform\Filament\Resources\SystemHealthSnapshots\Pages;

use App\Domain\Platform\Filament\Resources\SystemHealthSnapshots\SystemHealthSnapshotResource;
use Filament\Resources\Pages\ListRecords;

class ListSystemHealthSnapshots extends ListRecords
{
    protected static string $resource = SystemHealthSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
