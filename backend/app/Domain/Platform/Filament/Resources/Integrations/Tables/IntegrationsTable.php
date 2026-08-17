<?php

namespace App\Domain\Platform\Filament\Resources\Integrations\Tables;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\IntegrationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\IntegrationController.
 * There is no destroy route on the old controller, so DeleteAction is
 * deliberately omitted here (canDelete() is also disabled on the
 * resource) rather than offering a delete that doesn't exist upstream.
 */
class IntegrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('category')->badge(),
                TextColumn::make('provider')->badge()->color('gray'),
                TextColumn::make('mode')
                    ->badge()
                    ->color(fn (string $state): string => $state === Integration::MODE_PRODUCTION ? 'success' : 'warning'),
                IconColumn::make('is_enabled')->boolean()->label('Enabled'),
                TextColumn::make('last_test_result')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?string $state): string => match ($state) {
                        Integration::TEST_RESULT_SUCCESS => 'success',
                        Integration::TEST_RESULT_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_tested_at')->dateTime()->placeholder('Never')->sortable(),
                TextColumn::make('sort_order')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        Integration::CATEGORY_OAUTH => 'OAuth',
                        Integration::CATEGORY_MAPS => 'Maps',
                        Integration::CATEGORY_ANALYTICS => 'Analytics',
                        Integration::CATEGORY_SOCIAL_LOGIN => 'Social login',
                        Integration::CATEGORY_AI => 'AI',
                        Integration::CATEGORY_COMMUNICATION => 'Communication',
                        Integration::CATEGORY_AUTOMATION => 'Automation',
                        Integration::CATEGORY_STORAGE => 'Storage',
                        Integration::CATEGORY_CUSTOM => 'Custom',
                    ]),
                SelectFilter::make('mode')
                    ->options([
                        Integration::MODE_SANDBOX => 'Sandbox',
                        Integration::MODE_PRODUCTION => 'Production',
                    ]),
                TernaryFilter::make('is_enabled'),
            ])
            ->recordActions([
                static::testAction(),
                static::enableAction(),
                static::disableAction(),
                EditAction::make(),
            ]);
    }

    protected static function testAction(): Action
    {
        return Action::make('test')
            ->label('Test connection')
            ->icon(Heroicon::BoltSlash)
            ->color('gray')
            ->action(function (Integration $record, IntegrationService $service): void {
                $log = $service->testConnection($record);

                if ($log->is_successful) {
                    Notification::make()->title('Connection successful.')->success()->send();
                } else {
                    Notification::make()->title($log->error_message ?? 'Connection failed.')->danger()->send();
                }
            });
    }

    protected static function enableAction(): Action
    {
        return Action::make('enable')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->visible(fn (Integration $record): bool => ! $record->is_enabled)
            ->action(function (Integration $record, IntegrationService $service): void {
                $service->enable($record);
                Notification::make()->title('Integration enabled.')->success()->send();
            });
    }

    protected static function disableAction(): Action
    {
        return Action::make('disable')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Integration $record): bool => $record->is_enabled)
            ->action(function (Integration $record, IntegrationService $service): void {
                $service->disable($record);
                Notification::make()->title('Integration disabled.')->success()->send();
            });
    }
}
