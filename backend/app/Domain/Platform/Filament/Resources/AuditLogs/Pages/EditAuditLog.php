<?php

namespace App\Domain\Platform\Filament\Resources\AuditLogs\Pages;

use App\Domain\Platform\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAuditLog extends EditRecord
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
