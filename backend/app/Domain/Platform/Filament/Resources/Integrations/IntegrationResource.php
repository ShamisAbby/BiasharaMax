<?php

namespace App\Domain\Platform\Filament\Resources\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Platform\Filament\Resources\Integrations\Pages\CreateIntegration;
use App\Domain\Platform\Filament\Resources\Integrations\Pages\EditIntegration;
use App\Domain\Platform\Filament\Resources\Integrations\Pages\ListIntegrations;
use App\Domain\Platform\Filament\Resources\Integrations\RelationManagers\LogsRelationManager;
use App\Domain\Platform\Filament\Resources\Integrations\Schemas\IntegrationForm;
use App\Domain\Platform\Filament\Resources\Integrations\Tables\IntegrationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'Integrations';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    public static function form(Schema $schema): Schema
    {
        return IntegrationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntegrationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    /**
     * No destroy route exists on IntegrationController — deliberately not
     * offering delete in Filament either.
     */
    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrations::route('/'),
            'create' => CreateIntegration::route('/create'),
            'edit' => EditIntegration::route('/{record}/edit'),
        ];
    }
}
