<?php

namespace App\Domain\Platform\Filament\Resources\Subscribers\Tables;

use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Mail\BroadcastEmail;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\SubscriberController and
 * the App\Domain\Subscription\Services\SubscriptionService methods it
 * calls (assign-plan, extend-trial, renew, suspend/restore/cancel,
 * send-email) — every action here delegates to the same service methods
 * the old Inertia UI uses, not reimplemented business logic.
 */
class SubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('business.owner.email')
                    ->label('Owner email')
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Subscription::STATUS_ACTIVE => 'success',
                        Subscription::STATUS_TRIALING => 'info',
                        Subscription::STATUS_PAST_DUE => 'warning',
                        Subscription::STATUS_SUSPENDED, Subscription::STATUS_CANCELED, Subscription::STATUS_EXPIRED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('billing_cycle')
                    ->placeholder('—'),
                TextColumn::make('current_period_end')
                    ->label('Period ends')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('trial_ends_at')
                    ->label('Trial ends')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('auto_renew')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Subscription::STATUS_TRIALING => 'Trialing',
                        Subscription::STATUS_ACTIVE => 'Active',
                        Subscription::STATUS_PAST_DUE => 'Past due',
                        Subscription::STATUS_SUSPENDED => 'Suspended',
                        Subscription::STATUS_CANCELED => 'Canceled',
                        Subscription::STATUS_EXPIRED => 'Expired',
                    ]),
                SelectFilter::make('subscription_plan_id')
                    ->label('Plan')
                    ->options(fn () => SubscriptionPlan::query()->orderBy('sort_order')->pluck('name', 'id')),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::assignPlanAction(),
                    static::extendTrialAction(),
                    static::renewAction(),
                ])->label('Manage')->icon(Heroicon::Cog6Tooth),

                ActionGroup::make([
                    static::suspendAction(),
                    static::restoreAction(),
                    static::cancelAction(),
                ])->label('Lifecycle')->icon(Heroicon::ArrowPath),

                static::sendEmailAction(),
            ]);
    }

    protected static function assignPlanAction(): Action
    {
        return Action::make('assignPlan')
            ->label('Assign plan')
            ->icon(Heroicon::CreditCard)
            ->schema([
                Select::make('subscription_plan_id')
                    ->label('Plan')
                    ->options(fn () => SubscriptionPlan::query()->orderBy('sort_order')->pluck('name', 'id'))
                    ->required(),
                Select::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'yearly' => 'Yearly',
                        'lifetime' => 'Lifetime',
                    ])
                    ->required(),
                TextInput::make('custom_price')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Leave blank to use the plan\'s standard price for this cycle.'),
            ])
            ->fillForm(fn (Subscription $record): array => [
                'subscription_plan_id' => $record->subscription_plan_id,
                'billing_cycle' => $record->billing_cycle,
                'custom_price' => $record->custom_price,
            ])
            ->action(function (Subscription $record, array $data, SubscriptionService $subscriptions): void {
                $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);

                $subscriptions->changePlan(
                    $record,
                    $plan,
                    $data['billing_cycle'],
                    $data['custom_price'] !== null ? (float) $data['custom_price'] : null,
                );

                Notification::make()->title('Plan assigned.')->success()->send();
            });
    }

    protected static function extendTrialAction(): Action
    {
        return Action::make('extendTrial')
            ->label('Extend trial')
            ->icon(Heroicon::Clock)
            ->schema([
                TextInput::make('days')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(90)
                    ->required(),
            ])
            ->action(function (Subscription $record, array $data, SubscriptionService $subscriptions): void {
                $subscriptions->extendTrial($record, (int) $data['days']);

                Notification::make()->title('Trial extended.')->success()->send();
            });
    }

    protected static function renewAction(): Action
    {
        return Action::make('renew')
            ->label('Record renewal payment')
            ->icon(Heroicon::Banknotes)
            ->schema([
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('payment_method')
                    ->maxLength(30),
                Textarea::make('notes'),
            ])
            ->action(function (Subscription $record, array $data, SubscriptionService $subscriptions): void {
                $subscriptions->renewWithPayment($record, [
                    'amount' => (float) $data['amount'],
                    'payment_method' => $data['payment_method'] ?: null,
                    'notes' => $data['notes'] ?: null,
                    'recorded_by' => Auth::guard('platform')->id(),
                ]);

                Notification::make()->title('Subscription renewed.')->success()->send();
            });
    }

    protected static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->color('danger')
            ->icon(Heroicon::PauseCircle)
            ->requiresConfirmation()
            ->visible(fn (Subscription $record): bool => $record->status !== Subscription::STATUS_SUSPENDED)
            ->action(function (Subscription $record, SubscriptionService $subscriptions): void {
                $subscriptions->suspend($record);

                Notification::make()->title('Subscription suspended.')->success()->send();
            });
    }

    protected static function restoreAction(): Action
    {
        return Action::make('restore')
            ->color('success')
            ->icon(Heroicon::PlayCircle)
            ->visible(fn (Subscription $record): bool => in_array($record->status, [
                Subscription::STATUS_SUSPENDED,
                Subscription::STATUS_CANCELED,
                Subscription::STATUS_EXPIRED,
            ], true))
            ->action(function (Subscription $record, SubscriptionService $subscriptions): void {
                $subscriptions->restore($record);

                Notification::make()->title('Subscription restored.')->success()->send();
            });
    }

    protected static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->color('danger')
            ->icon(Heroicon::XCircle)
            ->requiresConfirmation()
            ->visible(fn (Subscription $record): bool => $record->status !== Subscription::STATUS_CANCELED)
            ->action(function (Subscription $record, SubscriptionService $subscriptions): void {
                $subscriptions->cancel($record);

                Notification::make()->title('Subscription canceled.')->success()->send();
            });
    }

    protected static function sendEmailAction(): Action
    {
        return Action::make('sendEmail')
            ->label('Email owner')
            ->icon(Heroicon::Envelope)
            ->color('gray')
            ->visible(fn (Subscription $record): bool => filled($record->business?->owner?->email))
            ->schema([
                TextInput::make('subject')->required()->maxLength(255),
                Textarea::make('body')->required()->maxLength(10000),
            ])
            ->action(function (Subscription $record, array $data): void {
                $owner = $record->business?->owner;

                Mail::to($owner->email)->send(new BroadcastEmail($data['subject'], $data['body'], $owner->name));

                Notification::make()->title('Email sent.')->success()->send();
            });
    }
}
