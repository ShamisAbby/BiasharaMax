<?php

namespace App\Domain\Platform\Filament\Resources\WebsiteTemplates;

use App\Domain\Platform\Filament\Resources\WebsiteTemplates\Pages\CreateWebsiteTemplate;
use App\Domain\Platform\Filament\Resources\WebsiteTemplates\Pages\EditWebsiteTemplate;
use App\Domain\Platform\Filament\Resources\WebsiteTemplates\Pages\ListWebsiteTemplates;
use App\Domain\Platform\Filament\Resources\WebsiteTemplates\Schemas\WebsiteTemplateForm;
use App\Domain\Platform\Filament\Resources\WebsiteTemplates\Tables\WebsiteTemplatesTable;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WebsiteTemplateResource extends Resource
{
    protected static ?string $model = WebsiteTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?string $navigationLabel = 'Website Templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['pages', 'versions']);
    }

    public static function form(Schema $schema): Schema
    {
        return WebsiteTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebsiteTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsiteTemplates::route('/'),
            'create' => CreateWebsiteTemplate::route('/create'),
            'edit' => EditWebsiteTemplate::route('/{record}/edit'),
        ];
    }
}
