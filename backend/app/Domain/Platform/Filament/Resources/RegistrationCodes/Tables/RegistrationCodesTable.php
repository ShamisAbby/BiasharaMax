<?php

namespace App\Domain\Platform\Filament\Resources\RegistrationCodes\Tables;

use App\Domain\Subscription\Models\RegistrationCode;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Mirrors App\Domain\Platform\Http\Controllers\RegistrationCodeController:
 * "generate" creates `quantity` codes in one go (not a single-record
 * create form — the old UI never has one either), and delete is blocked
 * for anything other than an unused (`available`) code, matching the
 * controller's abort_unless() guard exactly rather than letting the
 * table's own delete silently succeed on a used code.
 */
class RegistrationCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('plan.name')
                    ->label('Plan'),
                TextColumn::make('billing_cycle'),
                TextColumn::make('duration_months')
                    ->label('Duration (months)'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RegistrationCode::STATUS_AVAILABLE => 'success',
                        RegistrationCode::STATUS_USED => 'gray',
                        RegistrationCode::STATUS_EXPIRED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('usedByBusiness.name')
                    ->label('Used by')
                    ->placeholder('—'),
                TextColumn::make('expires_at')
                    ->date()
                    ->placeholder('Never'),
                TextColumn::make('createdBy.name')
                    ->label('Created by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        RegistrationCode::STATUS_AVAILABLE => 'Available',
                        RegistrationCode::STATUS_USED => 'Used',
                        RegistrationCode::STATUS_EXPIRED => 'Expired',
                    ]),
            ])
            ->headerActions([
                static::generateAction(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (RegistrationCode $record): bool => $record->status === RegistrationCode::STATUS_AVAILABLE),
            ]);
    }

    protected static function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Generate codes')
            ->icon(Heroicon::Plus)
            ->schema([
                Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn () => SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->required(),
                Select::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'yearly' => 'Yearly',
                    ])
                    ->required(),
                TextInput::make('duration_months')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(120)
                    ->required(),
                TextInput::make('quantity')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(1)
                    ->required(),
                DatePicker::make('expires_at')
                    ->minDate(now()->addDay())
                    ->native(false),
                Textarea::make('description')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $quantity = (int) $data['quantity'];
                unset($data['quantity']);

                $createdBy = Auth::guard('platform')->id();

                for ($i = 0; $i < $quantity; $i++) {
                    RegistrationCode::query()->create([
                        ...$data,
                        'code' => RegistrationCode::generate(),
                        'created_by' => $createdBy,
                    ]);
                }

                Notification::make()->title("{$quantity} code(s) generated.")->success()->send();
            });
    }
}
