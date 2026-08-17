<?php

namespace App\Domain\Platform\Filament\Resources\PlatformUsers;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Filament\Resources\PlatformUsers\Pages\CreatePlatformUser;
use App\Domain\Platform\Filament\Resources\PlatformUsers\Pages\EditPlatformUser;
use App\Domain\Platform\Filament\Resources\PlatformUsers\Pages\ListPlatformUsers;
use App\Domain\Platform\Filament\Resources\PlatformUsers\Schemas\PlatformUserForm;
use App\Domain\Platform\Filament\Resources\PlatformUsers\Tables\PlatformUsersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Labeled "Staff" in navigation (getPluralModelLabel below) to match the
 * old route/page name (routes/platform.php's `staff.` prefix,
 * Pages/Platform/Staff/Index.tsx) even though the model is PlatformUser.
 * Gated on platform_users.manage — same permission slug
 * routes/platform.php's `platform.permission:platform_users.manage`
 * middleware already used for every one of these routes.
 */
class PlatformUserResource extends Resource
{
    protected static ?string $model = PlatformUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    public static function form(Schema $schema): Schema
    {
        return PlatformUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformUsers::route('/'),
            'create' => CreatePlatformUser::route('/create'),
            'edit' => EditPlatformUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::hasManagePermission();
    }

    public static function canCreate(): bool
    {
        return static::hasManagePermission();
    }

    public static function canEdit(mixed $record): bool
    {
        return static::hasManagePermission();
    }

    public static function canDelete(mixed $record): bool
    {
        return static::hasManagePermission();
    }

    private static function hasManagePermission(): bool
    {
        return Auth::guard('platform')->user()?->hasPlatformPermission('platform_users.manage') ?? false;
    }
}
