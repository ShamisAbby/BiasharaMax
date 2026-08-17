<?php

namespace App\Domain\Platform\Filament\Resources\PaymentTransactions\Tables;

use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Services\PaymentTransactionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\PaymentTransactionController
 * / PaymentTransactionService: retry/refund catch service exceptions and
 * surface the message via a notification instead of a raw 500, matching
 * the controller's try/catch -> withErrors() pattern.
 */
class PaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('amount')
                    ->money(fn (PaymentTransaction $record): string => $record->currency ?? 'TZS')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PaymentTransaction::STATUS_SUCCESSFUL => 'success',
                        PaymentTransaction::STATUS_PENDING, PaymentTransaction::STATUS_PROCESSING => 'warning',
                        PaymentTransaction::STATUS_FAILED, PaymentTransaction::STATUS_CANCELLED, PaymentTransaction::STATUS_EXPIRED => 'danger',
                        PaymentTransaction::STATUS_REFUNDED, PaymentTransaction::STATUS_PARTIALLY_REFUNDED => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method')
                    ->placeholder('—'),
                TextColumn::make('gateway.name')
                    ->label('Gateway')
                    ->placeholder('Manual'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        PaymentTransaction::STATUS_PENDING => 'Pending',
                        PaymentTransaction::STATUS_PROCESSING => 'Processing',
                        PaymentTransaction::STATUS_SUCCESSFUL => 'Successful',
                        PaymentTransaction::STATUS_FAILED => 'Failed',
                        PaymentTransaction::STATUS_CANCELLED => 'Cancelled',
                        PaymentTransaction::STATUS_REFUNDED => 'Refunded',
                        PaymentTransaction::STATUS_PARTIALLY_REFUNDED => 'Partially refunded',
                        PaymentTransaction::STATUS_EXPIRED => 'Expired',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        PaymentTransaction::TYPE_SUBSCRIPTION_PAYMENT => 'Subscription payment',
                        PaymentTransaction::TYPE_LICENSE_PAYMENT => 'License payment',
                        PaymentTransaction::TYPE_RENEWAL => 'Renewal',
                        PaymentTransaction::TYPE_UPGRADE => 'Upgrade',
                        PaymentTransaction::TYPE_MANUAL => 'Manual',
                    ]),
                SelectFilter::make('payment_gateway_id')
                    ->label('Gateway')
                    ->options(fn () => PaymentGateway::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->headerActions([
                static::recordManualAction(),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::retryAction(),
                    static::approveAction(),
                    static::refundAction(),
                ]),
            ]);
    }

    protected static function recordManualAction(): Action
    {
        return Action::make('recordManual')
            ->label('Record payment')
            ->icon(Heroicon::Plus)
            ->schema([
                Select::make('business_id')
                    ->label('Business')
                    ->options(fn () => Business::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('payment_gateway_id')
                    ->label('Gateway')
                    ->options(fn () => PaymentGateway::query()->orderBy('name')->pluck('name', 'id')),
                Select::make('type')
                    ->options([
                        PaymentTransaction::TYPE_SUBSCRIPTION_PAYMENT => 'Subscription payment',
                        PaymentTransaction::TYPE_LICENSE_PAYMENT => 'License payment',
                        PaymentTransaction::TYPE_RENEWAL => 'Renewal',
                        PaymentTransaction::TYPE_UPGRADE => 'Upgrade',
                        PaymentTransaction::TYPE_MANUAL => 'Manual',
                    ])
                    ->required(),
                TextInput::make('amount')->numeric()->minValue(0.01)->required(),
                TextInput::make('currency')->maxLength(3)->default('TZS')->required(),
                TextInput::make('payment_method')->maxLength(30)->required(),
                TextInput::make('invoice_number')->maxLength(255),
                TextInput::make('tax_amount')->numeric()->minValue(0),
                TextInput::make('discount_amount')->numeric()->minValue(0),
                TextInput::make('fee_amount')->numeric()->minValue(0),
                TextInput::make('commission_amount')->numeric()->minValue(0),
                DateTimePicker::make('paid_at')->native(false),
                Textarea::make('notes')->columnSpanFull(),
            ])
            ->action(function (array $data, PaymentTransactionService $service): void {
                $service->recordManual($data, Auth::guard('platform')->user());

                Notification::make()->title('Payment recorded.')->success()->send();
            });
    }

    protected static function retryAction(): Action
    {
        return Action::make('retry')
            ->icon(Heroicon::ArrowPath)
            ->action(function (PaymentTransaction $record, PaymentTransactionService $service): void {
                try {
                    $service->retry($record, Auth::guard('platform')->user());
                    Notification::make()->title('Payment retried.')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (PaymentTransaction $record): bool => $record->status !== PaymentTransaction::STATUS_SUCCESSFUL)
            ->action(function (PaymentTransaction $record, PaymentTransactionService $service): void {
                $service->manuallyApprove($record, Auth::guard('platform')->user());

                Notification::make()->title('Payment approved.')->success()->send();
            });
    }

    protected static function refundAction(): Action
    {
        return Action::make('refund')
            ->icon(Heroicon::ReceiptRefund)
            ->color('danger')
            ->visible(fn (PaymentTransaction $record): bool => $record->status === PaymentTransaction::STATUS_SUCCESSFUL)
            ->schema([
                TextInput::make('amount')->numeric()->minValue(0.01)->required(),
                Textarea::make('reason'),
            ])
            ->action(function (PaymentTransaction $record, array $data, PaymentTransactionService $service): void {
                try {
                    $service->refund($record, (string) $data['amount'], Auth::guard('platform')->user(), $data['reason'] ?? null);
                    Notification::make()->title('Payment refunded.')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
