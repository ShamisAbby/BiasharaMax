<?php

namespace App\Domain\Platform\Filament\Resources\NotificationCampaigns;

use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Platform\Filament\Resources\NotificationCampaigns\Pages\CreateNotificationCampaign;
use App\Domain\Platform\Filament\Resources\NotificationCampaigns\Pages\ListNotificationCampaigns;
use App\Domain\Platform\Filament\Resources\NotificationCampaigns\Schemas\NotificationCampaignForm;
use App\Domain\Platform\Filament\Resources\NotificationCampaigns\Tables\NotificationCampaignsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationCampaignResource extends Resource
{
    protected static ?string $model = NotificationCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Notifications';

    protected static ?string $navigationLabel = 'Notification Campaigns';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('deliveries');
    }

    public static function form(Schema $schema): Schema
    {
        return NotificationCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationCampaignsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationCampaigns::route('/'),
            'create' => CreateNotificationCampaign::route('/create'),
        ];
    }
}
