<?php

namespace App\Domain\Platform\Filament\Resources\BackupRecords\Pages;

use App\Domain\Platform\Filament\Resources\BackupRecords\BackupRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListBackupRecords extends ListRecords
{
    protected static string $resource = BackupRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
