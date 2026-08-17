<?php

namespace App\Domain\Platform\Filament\Resources\SupportAgents;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Filament\Resources\SupportAgents\Pages\CreateSupportAgent;
use App\Domain\Platform\Filament\Resources\SupportAgents\Pages\EditSupportAgent;
use App\Domain\Platform\Filament\Resources\SupportAgents\Pages\ListSupportAgents;
use App\Domain\Support\Models\SupportAgent;
use App\Domain\Support\Models\SupportDepartment;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportAgentResource extends Resource
{
    protected static ?string $model = SupportAgent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Support Agents';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform_user_id')
                ->label('Staff member')
                ->options(fn () => PlatformUser::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->unique(table: SupportAgent::class, ignoreRecord: true)
                ->disabledOn('edit'),
            Select::make('support_department_id')
                ->label('Department')
                ->options(fn () => SupportDepartment::query()->orderBy('name')->pluck('name', 'id')),
            TextInput::make('max_concurrent_tickets')
                ->numeric()
                ->integer()
                ->minValue(1),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platformUser.name')
                    ->label('Staff member')
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('—'),
                TextColumn::make('max_concurrent_tickets')
                    ->label('Max tickets'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportAgents::route('/'),
            'create' => CreateSupportAgent::route('/create'),
            'edit' => EditSupportAgent::route('/{record}/edit'),
        ];
    }
}
