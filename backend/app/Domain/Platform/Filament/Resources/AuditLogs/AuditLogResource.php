<?php

namespace App\Domain\Platform\Filament\Resources\AuditLogs;

use App\Domain\Platform\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Domain\Platform\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Domain\Platform\Filament\Resources\AuditLogs\Schemas\AuditLogInfolist;
use App\Domain\Platform\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Domain\Shared\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only, matching AuditLogController — an immutable trail, never
 * created or edited through this UI (the make:filament-resource --view
 * scaffold's Create/Edit page classes and AuditLogForm schema are left on
 * disk but unreferenced, same as BusinessResource; see its docblock).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'action';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    public static function infolist(Schema $schema): Schema
    {
        return AuditLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
