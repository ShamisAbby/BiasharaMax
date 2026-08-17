<?php

namespace App\Domain\Platform\Filament\Resources\PlatformAnnouncements;

use App\Domain\Platform\Filament\Resources\PlatformAnnouncements\Pages\CreatePlatformAnnouncement;
use App\Domain\Platform\Filament\Resources\PlatformAnnouncements\Pages\EditPlatformAnnouncement;
use App\Domain\Platform\Filament\Resources\PlatformAnnouncements\Pages\ListPlatformAnnouncements;
use App\Domain\Support\Models\PlatformAnnouncement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlatformAnnouncementResource extends Resource
{
    protected static ?string $model = PlatformAnnouncement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSpeakerWave;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Announcements';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Select::make('audience')
                ->options([
                    PlatformAnnouncement::AUDIENCE_ALL => 'All',
                    PlatformAnnouncement::AUDIENCE_BUSINESSES => 'Businesses',
                    PlatformAnnouncement::AUDIENCE_PLATFORM_STAFF => 'Platform staff',
                ])
                ->required(),
            DateTimePicker::make('starts_at')->native(false),
            DateTimePicker::make('ends_at')->native(false)->afterOrEqual('starts_at'),
            Toggle::make('is_active')->default(true),
            Textarea::make('body')->required()->rows(6)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('audience')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('starts_at')->dateTime()->placeholder('—'),
                TextColumn::make('ends_at')->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformAnnouncements::route('/'),
            'create' => CreatePlatformAnnouncement::route('/create'),
            'edit' => EditPlatformAnnouncement::route('/{record}/edit'),
        ];
    }
}
