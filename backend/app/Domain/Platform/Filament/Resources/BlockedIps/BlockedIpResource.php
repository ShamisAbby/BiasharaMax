<?php

namespace App\Domain\Platform\Filament\Resources\BlockedIps;

use App\Domain\Platform\Filament\Resources\BlockedIps\Pages\CreateBlockedIp;
use App\Domain\Platform\Filament\Resources\BlockedIps\Pages\ListBlockedIps;
use App\Domain\Security\Models\BlockedIp;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlockedIpResource extends Resource
{
    protected static ?string $model = BlockedIp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Blocked IPs';

    protected static ?string $recordTitleAttribute = 'ip_address';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('ip_address')->required()->ip(),
            Textarea::make('reason')->maxLength(500),
            Toggle::make('is_permanent')->live(),
            DateTimePicker::make('expires_at')
                ->native(false)
                ->visible(fn (callable $get): bool => ! $get('is_permanent')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ip_address')->searchable()->fontFamily('mono'),
                TextColumn::make('reason')->limit(50)->placeholder('—'),
                IconColumn::make('is_permanent')->boolean(),
                TextColumn::make('expires_at')->dateTime()->placeholder('Never'),
                TextColumn::make('blockedBy.name')->label('Blocked by')->placeholder('—'),
            ])
            ->recordActions([
                DeleteAction::make()->label('Unblock'),
            ]);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlockedIps::route('/'),
            'create' => CreateBlockedIp::route('/create'),
        ];
    }
}
