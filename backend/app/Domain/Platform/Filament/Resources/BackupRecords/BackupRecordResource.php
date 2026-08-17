<?php

namespace App\Domain\Platform\Filament\Resources\BackupRecords;

use App\Domain\Monitoring\Models\BackupRecord;
use App\Domain\Platform\Filament\Resources\BackupRecords\Pages\ListBackupRecords;
use App\Domain\Platform\Filament\Resources\BackupRecords\Tables\BackupRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BackupRecordResource extends Resource
{
    protected static ?string $model = BackupRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Backups';

    public static function table(Table $table): Table
    {
        return BackupRecordsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBackupRecords::route('/'),
        ];
    }
}
