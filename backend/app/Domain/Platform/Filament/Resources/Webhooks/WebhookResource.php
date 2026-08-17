<?php

namespace App\Domain\Platform\Filament\Resources\Webhooks;

use App\Domain\Developer\Models\Webhook;
use App\Domain\Platform\Filament\Resources\Webhooks\Pages\CreateWebhook;
use App\Domain\Platform\Filament\Resources\Webhooks\Pages\EditWebhook;
use App\Domain\Platform\Filament\Resources\Webhooks\Pages\ListWebhooks;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Per-delivery retry (WebhookController::retryDelivery) isn't ported in
 * this pass — flagged as a follow-up, not silently dropped.
 */
class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Webhooks';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('url')->required()->url()->maxLength(255),
            TagsInput::make('events')->required(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('url')->limit(40),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('deliveries_count')->label('Deliveries')->counts('deliveries'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhooks::route('/'),
            'create' => CreateWebhook::route('/create'),
            'edit' => EditWebhook::route('/{record}/edit'),
        ];
    }
}
