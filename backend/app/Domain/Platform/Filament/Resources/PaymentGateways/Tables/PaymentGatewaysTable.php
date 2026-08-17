<?php

namespace App\Domain\Platform\Filament\Resources\PaymentGateways\Tables;

use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Services\PaymentGatewayService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentGatewaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider')
                    ->badge(),
                TextColumn::make('mode')
                    ->badge()
                    ->color(fn (string $state): string => $state === PaymentGateway::MODE_PRODUCTION ? 'success' : 'warning'),
                IconColumn::make('is_enabled')
                    ->boolean(),
                TextColumn::make('health_status')
                    ->label('Health')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PaymentGateway::HEALTH_ONLINE => 'success',
                        PaymentGateway::HEALTH_DEGRADED => 'warning',
                        PaymentGateway::HEALTH_OFFLINE => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->counts('transactions'),
            ])
            ->recordActions([
                EditAction::make(),
                static::enableAction(),
                static::disableAction(),
                static::checkHealthAction(),
            ]);
    }

    protected static function enableAction(): Action
    {
        return Action::make('enable')
            ->color('success')
            ->icon(Heroicon::CheckCircle)
            ->visible(fn (PaymentGateway $record): bool => ! $record->is_enabled)
            ->action(fn (PaymentGateway $record, PaymentGatewayService $service) => $service->enable($record));
    }

    protected static function disableAction(): Action
    {
        return Action::make('disable')
            ->color('gray')
            ->icon(Heroicon::MinusCircle)
            ->visible(fn (PaymentGateway $record): bool => $record->is_enabled)
            ->action(fn (PaymentGateway $record, PaymentGatewayService $service) => $service->disable($record));
    }

    protected static function checkHealthAction(): Action
    {
        return Action::make('checkHealth')
            ->label('Check health')
            ->icon(Heroicon::Heart)
            ->action(function (PaymentGateway $record, PaymentGatewayService $service): void {
                $service->checkHealth($record);

                Notification::make()->title('Health check complete.')->success()->send();
            });
    }
}
