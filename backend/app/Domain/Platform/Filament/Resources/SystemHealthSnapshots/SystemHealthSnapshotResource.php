<?php

namespace App\Domain\Platform\Filament\Resources\SystemHealthSnapshots;

use App\Domain\Monitoring\Models\SystemHealthSnapshot;
use App\Domain\Platform\Filament\Resources\SystemHealthSnapshots\Pages\ListSystemHealthSnapshots;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only, system-generated — matches MonitoringController (no
 * create/edit/delete route, snapshots are recorded by a scheduled
 * command). The live "current snapshot" + 24h trend chart from the old
 * dashboard isn't reproduced here; this is the raw history table it was
 * built from, which covers the same underlying data for review purposes.
 */
class SystemHealthSnapshotResource extends Resource
{
    protected static ?string $model = SystemHealthSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Monitoring';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('recorded_at', 'desc')
            ->columns([
                TextColumn::make('recorded_at')->dateTime()->sortable(),
                TextColumn::make('health_score')->numeric(1)->suffix('%'),
                TextColumn::make('cpu_usage')->label('CPU')->numeric(1)->suffix('%'),
                TextColumn::make('memory_usage')->label('Memory')->numeric(1)->suffix('%'),
                TextColumn::make('disk_usage')->label('Disk')->numeric(1)->suffix('%'),
                TextColumn::make('queue_pending')->label('Queue pending'),
                TextColumn::make('queue_failed')->label('Queue failed'),
                TextColumn::make('db_response_time_ms')->label('DB (ms)'),
                TextColumn::make('redis_status')->badge(),
                TextColumn::make('horizon_status')->badge(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemHealthSnapshots::route('/'),
        ];
    }
}
