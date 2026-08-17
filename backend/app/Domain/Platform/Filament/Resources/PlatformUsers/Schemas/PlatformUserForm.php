<?php

namespace App\Domain\Platform\Filament\Resources\PlatformUsers\Schemas;

use App\Domain\Authentication\Support\UserIdentityRules;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Follows PlatformAdminInviteRequest / PlatformAdminUpdateRequest: name,
 * email (create only — never editable after invite, same as the old
 * form), platform_role_id. No password field — invited accounts get a
 * random unusable password and set their own via the existing signed
 * invitation link (App\Domain\Platform\Services\PlatformAdminInvitationService),
 * and status changes go through the activate/deactivate table actions,
 * not a raw field edit.
 *
 * `username` and `phone` are additions beyond those old requests — both
 * are unique in the database (see the 2026_08_06 migration) and required
 * here. Existing accounts predate the columns and hold NULL, so they will
 * be prompted for both the first time anyone edits them.
 */
class PlatformUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->required()
                    ->maxLength(UserIdentityRules::USERNAME_MAX_LENGTH)
                    ->regex(UserIdentityRules::USERNAME_REGEX)
                    ->validationMessages(['regex' => UserIdentityRules::USERNAME_MESSAGE])
                    ->helperText('Up to 15 characters. Letters, numbers and underscores only.')
                    ->unique(ignoreRecord: true),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('phone')
                    ->label('Phone number')
                    ->tel()
                    ->required()
                    ->maxLength(UserIdentityRules::PHONE_MAX_LENGTH)
                    ->unique(ignoreRecord: true),
                // Multi-select over the pivot: an admin may hold any
                // number of roles and receives the union of their
                // permissions. `relationship()` makes Filament read and
                // write `platform_role_platform_user` directly, so no
                // manual sync is needed on edit.
                // Required on purpose: hasPlatformPermission() treats an
                // account with NO roles as unrestricted (a carry-over so
                // the original seeded Super Admin can't lock itself out),
                // so saving this empty would silently create a
                // full-access admin. Requiring at least one role keeps
                // that path unreachable from the UI.
                Select::make('platformRoles')
                    ->label('Platform roles')
                    ->relationship('platformRoles', 'name')
                    ->multiple()
                    ->required()
                    ->preload()
                    ->searchable()
                    ->native(false)
                    ->helperText('Permissions are the combination of every role assigned.')
                    ->columnSpanFull(),
            ]);
    }
}
