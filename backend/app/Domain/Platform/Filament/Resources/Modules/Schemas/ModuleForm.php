<?php

namespace App\Domain\Platform\Filament\Resources\Modules\Schemas;

use App\Domain\Business\Models\BusinessType;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Field set mirrors App\Domain\Platform\Http\Requests\ModuleRequest. */
class ModuleForm
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
                        ->unique(table: Module::class, ignoreRecord: true),
                    TextInput::make('version')->required()->maxLength(20),
                    TextInput::make('icon')->maxLength(60),
                    TextInput::make('category')->maxLength(60),
                    Select::make('visibility')
                        ->options([
                            Module::VISIBILITY_PUBLIC => 'Public',
                            Module::VISIBILITY_HIDDEN => 'Hidden',
                            Module::VISIBILITY_BETA => 'Beta',
                        ])
                        ->required(),
                    TextInput::make('sort_order')->numeric()->integer()->default(0)->required(),
                    Textarea::make('description')->columnSpanFull(),
                ]),

            Section::make('Support & flags')
                ->columns(4)
                ->schema([
                    Toggle::make('is_premium'),
                    Toggle::make('is_desktop_supported'),
                    Toggle::make('is_cloud_supported'),
                    Toggle::make('is_hybrid_supported'),
                ]),

            Section::make('Dependencies & availability')
                ->columns(3)
                ->schema([
                    Select::make('dependencies')
                        ->relationship('dependencies', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn (?Module $record) => Module::query()
                            ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                            ->orderBy('name')
                            ->pluck('name', 'id')),
                    Select::make('subscriptionPlans')
                        ->label('Subscription plans')
                        ->relationship('subscriptionPlans', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn () => SubscriptionPlan::query()->orderBy('sort_order')->pluck('name', 'id')),
                    Select::make('businessTypes')
                        ->label('Business types')
                        ->relationship('businessTypes', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn () => BusinessType::query()->orderBy('name')->pluck('name', 'id')),
                ]),
        ]);
    }
}
