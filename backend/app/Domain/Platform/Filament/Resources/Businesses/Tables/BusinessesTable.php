<?php

namespace App\Domain\Platform\Filament\Resources\Businesses\Tables;

use App\Domain\Business\Models\Business;
use App\Domain\Platform\Http\Controllers\ImpersonationController;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Mirrors BusinessManagementController exactly (see BusinessResource's
 * docblock) — every action below does the same thing the old Inertia
 * controller method did, just as a Filament table Action instead of a
 * route handler.
 */
class BusinessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner.email')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Business::STATUS_ACTIVE => 'success',
                        Business::STATUS_TRIAL => 'info',
                        Business::STATUS_SUSPENDED => 'danger',
                        Business::STATUS_EXPIRED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('subscription.plan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Business::STATUS_TRIAL => 'Trial',
                        Business::STATUS_ACTIVE => 'Active',
                        Business::STATUS_SUSPENDED => 'Suspended',
                        Business::STATUS_EXPIRED => 'Expired',
                    ]),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->visible(fn (Business $record): bool => $record->status !== Business::STATUS_SUSPENDED)
                    ->requiresConfirmation()
                    ->action(function (Business $record): void {
                        $record->update(['status' => Business::STATUS_SUSPENDED]);

                        Notification::make()->title('Business suspended')->success()->send();
                    }),
                Action::make('activate')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Business $record): bool => $record->status === Business::STATUS_SUSPENDED)
                    ->requiresConfirmation()
                    ->action(function (Business $record): void {
                        $record->update(['status' => Business::STATUS_ACTIVE]);

                        Notification::make()->title('Business activated')->success()->send();
                    }),
                Action::make('updateSubscription')
                    ->label('Change plan')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Select::make('subscription_plan_id')
                            ->label('Plan')
                            ->options(fn (): array => SubscriptionPlan::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                            ->required(),
                        Select::make('status')
                            ->options([
                                'trialing' => 'Trialing',
                                'active' => 'Active',
                                'past_due' => 'Past due',
                                'canceled' => 'Canceled',
                                'expired' => 'Expired',
                            ])
                            ->required(),
                    ])
                    ->fillForm(fn (Business $record): array => [
                        'subscription_plan_id' => $record->subscription?->subscription_plan_id,
                        'status' => $record->subscription?->status,
                    ])
                    ->action(function (Business $record, array $data): void {
                        $record->subscription()->updateOrCreate(
                            ['business_id' => $record->id],
                            [
                                'subscription_plan_id' => $data['subscription_plan_id'],
                                'status' => $data['status'],
                            ],
                        );

                        Notification::make()->title('Subscription updated')->success()->send();
                    }),
                Action::make('impersonate')
                    ->color('warning')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Business $record): bool => $record->owner !== null)
                    ->requiresConfirmation()
                    ->modalDescription('You will be logged in as this business\'s owner. This is logged in the impersonation audit trail.')
                    ->action(fn (Business $record) => app(ImpersonationController::class)->start(request(), $record)),
            ]);
    }
}
