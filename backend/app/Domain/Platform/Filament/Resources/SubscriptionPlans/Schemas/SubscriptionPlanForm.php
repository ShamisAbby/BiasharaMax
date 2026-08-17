<?php

namespace App\Domain\Platform\Filament\Resources\SubscriptionPlans\Schemas;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Field set mirrors App\Domain\Platform\Http\Requests\SubscriptionPlanRequest
 * exactly. Only the decimal price columns (price_monthly etc.) are edited
 * here — the matching `_minor` integer columns are kept in sync
 * automatically by the model's SyncsMoneyMinorColumns trait, same as the
 * old controller's behavior (it only ever mass-assigns the decimal
 * fields too).
 */
class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Plan details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', str($state)->slug()) : null),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(table: SubscriptionPlan::class, ignoreRecord: true),
                    Select::make('type')
                        ->options([
                            SubscriptionPlan::TYPE_STANDARD => 'Standard',
                            SubscriptionPlan::TYPE_ENTERPRISE => 'Enterprise',
                        ])
                        ->required(),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->integer()
                        ->default(0)
                        ->required(),
                    Textarea::make('description')
                        ->columnSpanFull(),
                ]),

            Section::make('Pricing')
                ->columns(4)
                ->schema([
                    TextInput::make('price_monthly')
                        ->label('Monthly price')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('price_quarterly')
                        ->label('Quarterly price')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('price_yearly')
                        ->label('Yearly price')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('price_lifetime')
                        ->label('Lifetime price')
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('trial_days')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->required(),
                ]),

            Section::make('Limits')
                ->description('Leave blank for unlimited.')
                ->columns(4)
                ->schema([
                    TextInput::make('max_users')->numeric()->integer()->minValue(0),
                    TextInput::make('max_branches')->numeric()->integer()->minValue(0),
                    TextInput::make('max_warehouses')->numeric()->integer()->minValue(0),
                    TextInput::make('max_products')->numeric()->integer()->minValue(0),
                    TextInput::make('max_employees')->numeric()->integer()->minValue(0),
                    TextInput::make('max_storage_mb')->label('Max storage (MB)')->numeric()->integer()->minValue(0),
                    TextInput::make('max_api_requests_per_day')->label('Max API requests / day')->numeric()->integer()->minValue(0),
                    TextInput::make('max_notifications_per_month')->label('Max notifications / month')->numeric()->integer()->minValue(0),
                ]),

            Section::make('Features')
                ->columns(2)
                ->schema([
                    TagsInput::make('features')
                        ->columnSpanFull()
                        ->helperText('Free-text feature bullets shown on the pricing page.'),
                    Toggle::make('is_active')->default(true),
                    Toggle::make('includes_website'),
                    Toggle::make('includes_ai'),
                    Toggle::make('includes_offline_sync'),
                    Toggle::make('includes_desktop_edition'),
                    Toggle::make('includes_reports'),
                ]),
        ]);
    }
}
