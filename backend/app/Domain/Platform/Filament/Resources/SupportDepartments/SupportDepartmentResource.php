<?php

namespace App\Domain\Platform\Filament\Resources\SupportDepartments;

use App\Domain\Platform\Filament\Resources\SupportDepartments\Pages\CreateSupportDepartment;
use App\Domain\Platform\Filament\Resources\SupportDepartments\Pages\EditSupportDepartment;
use App\Domain\Platform\Filament\Resources\SupportDepartments\Pages\ListSupportDepartments;
use App\Domain\Support\Models\SupportDepartment;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * No delete — matches App\Domain\Platform\Http\Controllers\SupportDepartmentController
 * exactly (only store/update routes exist).
 */
class SupportDepartmentResource extends Resource
{
    protected static ?string $model = SupportDepartment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Support Departments';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', str($state)->slug()) : null),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(table: SupportDepartment::class, ignoreRecord: true)
                ->disabledOn('edit'),
            Textarea::make('description'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->color('gray'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportDepartments::route('/'),
            'create' => CreateSupportDepartment::route('/create'),
            'edit' => EditSupportDepartment::route('/{record}/edit'),
        ];
    }
}
