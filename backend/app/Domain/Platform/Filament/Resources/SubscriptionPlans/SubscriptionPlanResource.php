<?php

namespace App\Domain\Platform\Filament\Resources\SubscriptionPlans;

use App\Domain\Platform\Filament\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Domain\Platform\Filament\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Domain\Platform\Filament\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Domain\Platform\Filament\Resources\SubscriptionPlans\Schemas\SubscriptionPlanForm;
use App\Domain\Platform\Filament\Resources\SubscriptionPlans\Tables\SubscriptionPlansTable;
use App\Domain\Subscription\Models\SubscriptionPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?string $navigationLabel = 'Plans';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('subscriptions');
    }

    public static function form(Schema $schema): Schema
    {
        return SubscriptionPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
