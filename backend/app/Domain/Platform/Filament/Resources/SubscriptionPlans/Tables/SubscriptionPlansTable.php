<?php

namespace App\Domain\Platform\Filament\Resources\SubscriptionPlans\Tables;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\SubscriptionPlanController:
 * activate/deactivate toggle is_active directly (no confirmation in the
 * old UI either), and delete is blocked with the exact same message when
 * the plan has subscribers, instead of relying on a DB-level FK
 * restriction the user would just see as a raw error.
 */
class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === SubscriptionPlan::TYPE_ENTERPRISE ? 'warning' : 'gray'),
                TextColumn::make('price_monthly')
                    ->label('Monthly')
                    ->money('TZS')
                    ->sortable(),
                TextColumn::make('price_yearly')
                    ->label('Yearly')
                    ->money('TZS')
                    ->sortable(),
                TextColumn::make('subscriptions_count')
                    ->label('Subscribers')
                    ->counts('subscriptions')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        SubscriptionPlan::TYPE_STANDARD => 'Standard',
                        SubscriptionPlan::TYPE_ENTERPRISE => 'Enterprise',
                    ]),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('activate')
                    ->color('success')
                    ->visible(fn (SubscriptionPlan $record): bool => ! $record->is_active)
                    ->action(fn (SubscriptionPlan $record) => $record->update(['is_active' => true])),
                Action::make('deactivate')
                    ->color('gray')
                    ->visible(fn (SubscriptionPlan $record): bool => $record->is_active)
                    ->action(fn (SubscriptionPlan $record) => $record->update(['is_active' => false])),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, SubscriptionPlan $record) {
                        if ($record->subscriptions()->exists()) {
                            Notification::make()
                                ->title('This plan has subscribers and cannot be deleted.')
                                ->body('Deactivate it instead.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }
}
