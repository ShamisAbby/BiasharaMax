<?php

namespace App\Domain\Platform\Filament\Resources\NotificationCampaigns\Tables;

use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Services\NotificationDispatchService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * No edit action — matches
 * App\Domain\Platform\Http\Controllers\NotificationCampaignController
 * (no update route). Per-delivery retry (the controller's
 * retryDelivery()) isn't ported in this pass — flagged as a follow-up
 * rather than silently dropped; campaign-level "send" is.
 */
class NotificationCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->badge(),
                TextColumn::make('audience_type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        NotificationCampaign::STATUS_SENT => 'success',
                        NotificationCampaign::STATUS_SENDING, NotificationCampaign::STATUS_SCHEDULED => 'warning',
                        NotificationCampaign::STATUS_FAILED, NotificationCampaign::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('deliveries_count')
                    ->label('Deliveries')
                    ->counts('deliveries'),
                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->placeholder('On demand'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        NotificationCampaign::STATUS_DRAFT => 'Draft',
                        NotificationCampaign::STATUS_SCHEDULED => 'Scheduled',
                        NotificationCampaign::STATUS_SENDING => 'Sending',
                        NotificationCampaign::STATUS_SENT => 'Sent',
                        NotificationCampaign::STATUS_FAILED => 'Failed',
                        NotificationCampaign::STATUS_CANCELLED => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                Action::make('send')
                    ->icon(Heroicon::PaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (NotificationCampaign $record): bool => in_array($record->status, [
                        NotificationCampaign::STATUS_DRAFT,
                        NotificationCampaign::STATUS_SCHEDULED,
                        NotificationCampaign::STATUS_FAILED,
                    ], true))
                    ->action(function (NotificationCampaign $record, NotificationDispatchService $service): void {
                        $service->sendCampaign($record);

                        Notification::make()->title('Campaign sent.')->success()->send();
                    }),
            ]);
    }
}
