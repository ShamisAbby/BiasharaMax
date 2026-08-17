<?php

namespace App\Domain\Platform\Filament\Resources\AuditLogs\Pages;

use App\Domain\Platform\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuditLog extends CreateRecord
{
    protected static string $resource = AuditLogResource::class;
}
