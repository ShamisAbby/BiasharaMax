<?php

namespace App\Domain\Platform\Filament\Resources\NotificationTemplates;

use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Platform\Filament\Resources\NotificationTemplates\Pages\CreateNotificationTemplate;
use App\Domain\Platform\Filament\Resources\NotificationTemplates\Pages\EditNotificationTemplate;
use App\Domain\Platform\Filament\Resources\NotificationTemplates\Pages\ListNotificationTemplates;
use App\Domain\Platform\Filament\Resources\NotificationTemplates\Schemas\NotificationTemplateForm;
use App\Domain\Platform\Filament\Resources\NotificationTemplates\Tables\NotificationTemplatesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Notifications';

    protected static ?string $navigationLabel = 'Notification Templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return NotificationTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationTemplates::route('/'),
            'create' => CreateNotificationTemplate::route('/create'),
            'edit' => EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
