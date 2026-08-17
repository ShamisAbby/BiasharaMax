<?php

namespace App\Domain\Platform\Filament\Resources\NotificationChannels;

use App\Domain\Notifications\Models\NotificationChannel;
use App\Domain\Platform\Filament\Resources\NotificationChannels\Pages\CreateNotificationChannel;
use App\Domain\Platform\Filament\Resources\NotificationChannels\Pages\EditNotificationChannel;
use App\Domain\Platform\Filament\Resources\NotificationChannels\Pages\ListNotificationChannels;
use App\Domain\Platform\Filament\Resources\NotificationChannels\Schemas\NotificationChannelForm;
use App\Domain\Platform\Filament\Resources\NotificationChannels\Tables\NotificationChannelsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NotificationChannelResource extends Resource
{
    protected static ?string $model = NotificationChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Notifications';

    protected static ?string $navigationLabel = 'Notification Channels';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return NotificationChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationChannelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationChannels::route('/'),
            'create' => CreateNotificationChannel::route('/create'),
            'edit' => EditNotificationChannel::route('/{record}/edit'),
        ];
    }
}
