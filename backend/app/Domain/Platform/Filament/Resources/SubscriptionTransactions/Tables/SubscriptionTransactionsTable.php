<?php

namespace App\Domain\Platform\Filament\Resources\SubscriptionTransactions\Tables;

use App\Domain\Subscription\Models\SubscriptionTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money(fn (SubscriptionTransaction $record): string => $record->currency ?? 'TZS')
                    ->sortable(),
                TextColumn::make('billing_cycle'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SubscriptionTransaction::STATUS_PAID => 'success',
                        SubscriptionTransaction::STATUS_PENDING => 'warning',
                        SubscriptionTransaction::STATUS_REFUNDED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method')
                    ->placeholder('—'),
                TextColumn::make('recordedBy.name')
                    ->label('Recorded by')
                    ->placeholder('—'),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        SubscriptionTransaction::STATUS_PAID => 'Paid',
                        SubscriptionTransaction::STATUS_PENDING => 'Pending',
                        SubscriptionTransaction::STATUS_REFUNDED => 'Refunded',
                    ]),
            ]);
    }
}
