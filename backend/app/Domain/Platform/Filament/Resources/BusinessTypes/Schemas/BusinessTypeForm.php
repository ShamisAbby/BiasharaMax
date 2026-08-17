<?php

namespace App\Domain\Platform\Filament\Resources\BusinessTypes\Schemas;

use App\Domain\Business\Models\BusinessType;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Field set mirrors App\Domain\Platform\Http\Requests\BusinessTypeRequest.
 * The `modules`/`subscriptionPlans` relationships are edited here as
 * multi-selects backed directly by the pivot tables (Filament's
 * relationship-aware Select saves via ->relationship(), equivalent to
 * the controller's manual ->sync() calls).
 */
class BusinessTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')
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
                        ->unique(table: BusinessType::class, ignoreRecord: true),
                    TextInput::make('icon')->maxLength(60),
                    ColorPicker::make('color'),
                    TextInput::make('default_currency')->maxLength(3),
                    TextInput::make('default_tax_rate')->numeric()->minValue(0)->maxValue(100),
                    TextInput::make('website_template')->maxLength(255),
                    TextInput::make('sort_order')->numeric()->integer()->default(0)->required(),
                    Textarea::make('description')->columnSpanFull(),
                ]),

            Section::make('Feature toggles')
                ->columns(4)
                ->schema([
                    Toggle::make('inventory_enabled'),
                    Toggle::make('pos_enabled'),
                    Toggle::make('accounting_enabled'),
                    Toggle::make('crm_enabled'),
                    Toggle::make('website_enabled'),
                    Toggle::make('online_ordering_enabled'),
                    Toggle::make('offline_mode_enabled'),
                    Toggle::make('desktop_edition_enabled'),
                ]),

            Section::make('Default limits')
                ->description('Leave blank for unlimited.')
                ->columns(4)
                ->schema([
                    TextInput::make('default_employee_limit')->numeric()->integer()->minValue(0),
                    TextInput::make('default_branch_limit')->numeric()->integer()->minValue(0),
                    TextInput::make('default_warehouse_limit')->numeric()->integer()->minValue(0),
                    TextInput::make('default_storage_limit_mb')->label('Default storage (MB)')->numeric()->integer()->minValue(0),
                ]),

            Section::make('Modules & plans')
                ->columns(2)
                ->schema([
                    Select::make('modules')
                        ->relationship('modules', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn () => Module::query()->orderBy('name')->pluck('name', 'id')),
                    Select::make('subscriptionPlans')
                        ->label('Subscription plans')
                        ->relationship('subscriptionPlans', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn () => SubscriptionPlan::query()->orderBy('sort_order')->pluck('name', 'id')),
                ]),
        ]);
    }
}
