<?php

namespace App\Domain\Platform\Filament\Resources\AuditLogs\Pages;

use App\Domain\Platform\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No create action — audit logs are an immutable, system-generated trail
 * (see AuditLogResource::canCreate(), which already returns false). The
 * generator scaffold hardcoded a CreateAction here regardless of
 * canCreate(), which opened a blank modal with no form fields and crashed
 * on insert; removed rather than gated, since this resource should never
 * offer manual creation.
 */
class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
