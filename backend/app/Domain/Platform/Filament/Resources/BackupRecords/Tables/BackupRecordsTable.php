<?php

namespace App\Domain\Platform\Filament\Resources\BackupRecords\Tables;

use App\Domain\Backup\Services\BackupService;
use App\Domain\Backup\Services\RestoreService;
use App\Domain\Monitoring\Models\BackupRecord;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\BackupController. Restore
 * is destructive — the old UI requires typing the backup's exact
 * filename to confirm before sending the request; that safety check is
 * replicated here exactly (via a required TextInput the action validates
 * against before calling RestoreService::restore()), not simplified away
 * to a plain confirmation dialog. `preview()` (a JSON diff modal) isn't
 * ported in this pass — flagged as a follow-up.
 */
class BackupRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        BackupRecord::STATUS_SUCCESS => 'success',
                        BackupRecord::STATUS_RUNNING => 'warning',
                        BackupRecord::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('triggered_by')
                    ->label('Trigger')
                    ->badge(),
                TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1048576, 2).' MB' : '—'),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        BackupRecord::TYPE_DATABASE => 'Database',
                        BackupRecord::TYPE_STORAGE => 'Storage',
                        BackupRecord::TYPE_FULL => 'Full',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        BackupRecord::STATUS_RUNNING => 'Running',
                        BackupRecord::STATUS_SUCCESS => 'Success',
                        BackupRecord::STATUS_FAILED => 'Failed',
                    ]),
            ])
            ->headerActions([
                static::runAction(),
            ])
            ->recordActions([
                static::downloadAction(),
                static::restoreAction(),
                DeleteAction::make(),
            ]);
    }

    protected static function runAction(): Action
    {
        return Action::make('run')
            ->label('Run backup')
            ->icon(Heroicon::Plus)
            ->schema([
                Select::make('type')
                    ->options([
                        BackupRecord::TYPE_DATABASE => 'Database',
                        BackupRecord::TYPE_STORAGE => 'Storage',
                        BackupRecord::TYPE_FULL => 'Full',
                    ])
                    ->required(),
            ])
            ->action(function (array $data, BackupService $service): void {
                $record = $service->run($data['type'], BackupRecord::TRIGGERED_MANUAL);

                if ($record->status === BackupRecord::STATUS_SUCCESS) {
                    Notification::make()->title('Backup completed.')->success()->send();
                } else {
                    Notification::make()->title('Backup failed.')->danger()->send();
                }
            });
    }

    protected static function downloadAction(): Action
    {
        return Action::make('download')
            ->icon(Heroicon::ArrowDownTray)
            ->visible(fn (BackupRecord $record): bool => $record->file_path && Storage::disk($record->disk)->exists($record->file_path))
            ->action(fn (BackupRecord $record) => Storage::disk($record->disk)->download($record->file_path));
    }

    protected static function restoreAction(): Action
    {
        return Action::make('restore')
            ->color('danger')
            ->icon(Heroicon::ExclamationTriangle)
            ->visible(fn (BackupRecord $record): bool => $record->status === BackupRecord::STATUS_SUCCESS)
            ->schema([
                TextInput::make('confirmation')
                    ->label('Type the backup filename to confirm')
                    ->required(),
            ])
            ->modalDescription('This overwrites current data with the backup\'s contents. This cannot be undone.')
            ->action(function (BackupRecord $record, array $data, RestoreService $service): void {
                $expectedFilename = basename($record->file_path ?? '');

                if ($data['confirmation'] !== $expectedFilename) {
                    Notification::make()->title('Filename confirmation does not match.')->danger()->send();

                    return;
                }

                try {
                    $service->restore($record);
                    Notification::make()->title('Restore completed.')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
