<?php

namespace App\Domain\Platform\Filament\Pages;

use App\Domain\Authentication\Support\UserIdentityRules;
use App\Domain\Platform\Filament\Concerns\ResolvesUploadPreviewsSameOrigin;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Filament's built-in profile page has no avatar field by default (just
 * name/email/password) — this adds one, backed by the exact same
 * `avatar` column/disk/validation/directory as the existing Inertia
 * flow's PlatformProfileController::uploadAvatar(), so both surfaces
 * read/write the same stored file rather than diverging.
 */
class EditProfile extends BaseEditProfile
{
    use ResolvesUploadPreviewsSameOrigin;

    /**
     * Filament's own profile form is a bare, unwrapped list of fields —
     * `getFormContentComponent()` renders it with no Section around it,
     * so on a full-width panel page the labels and inputs end up marooned
     * at opposite edges of the screen. These two sections put the form on
     * the same card surface as the rest of the panel.
     *
     * Deliberately NOT `->aside()`: that splits each section into a
     * heading column beside a field column, and since the two columns
     * stretch to a shared height, a short heading next to a tall field
     * stack leaves a large empty panel on the left. Heading above,
     * fields in a two-column grid below, reads better and stays compact.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profile')
                ->description('How your account appears to other people across the platform.')
                ->icon(Heroicon::OutlinedUserCircle)
                ->columns(2)
                ->schema([
                    $this->getAvatarFormComponent()
                        ->helperText('JPG, PNG or WebP, up to 2 MB.')
                        ->columnSpanFull(),
                    $this->getNameFormComponent(),
                    $this->getUsernameFormComponent(),
                    $this->getEmailFormComponent(),
                    $this->getPhoneFormComponent(),
                ]),

            // Order matters here: current password, then the new one,
            // then its confirmation. Filament's default puts the current
            // password LAST and only reveals it once something has
            // changed, which reads backwards — you fill in a new password
            // before being asked to prove you know the old one.
            Section::make('Password')
                ->description('Leave blank to keep your current password.')
                ->icon(Heroicon::OutlinedKey)
                ->columns(2)
                ->schema([
                    $this->getCurrentPasswordFormComponent()->columnSpanFull(),
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                ]),
        ]);
    }

    /**
     * Drops the `inlineLabel()` Filament applies to every field on the
     * non-simple profile page. The sections above already supply the
     * left-hand column, so keeping inline labels too would nest one
     * label column inside another.
     */
    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->getUser())
            ->operation('edit')
            ->statePath('data');
    }

    /**
     * The panel runs at full width so the dashboard grid can use the
     * whole screen, but a two-column form doesn't benefit from that —
     * on a wide monitor the inputs would stretch to several thousand
     * pixels. Capping this one page keeps the fields a readable length.
     */
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::FiveExtraLarge;
    }

    /**
     * Unique across platform staff, and — because it is also accepted at
     * sign-in — the format is deliberately narrow: letters, digits and
     * underscores only, so it can never be mistaken for an email address
     * when the login controller decides which column to match on.
     */
    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->maxLength(UserIdentityRules::USERNAME_MAX_LENGTH)
            ->regex(UserIdentityRules::USERNAME_REGEX)
            ->validationMessages(['regex' => UserIdentityRules::USERNAME_MESSAGE])
            ->helperText('Up to 15 characters. Letters, numbers and underscores only. You can sign in with this instead of your email.')
            ->unique(ignoreRecord: true);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Phone number')
            ->tel()
            ->required()
            ->maxLength(UserIdentityRules::PHONE_MAX_LENGTH)
            ->unique(ignoreRecord: true);
    }

    /**
     * Always visible, rather than Filament's default of appearing only
     * once the password or email has already been edited. It is the first
     * field in the section, so it has to be on screen for the order to
     * mean anything — a field that materialises later can't be "entered
     * first". It stays *required* only when the password or email
     * actually changes, so unrelated edits (name, avatar) don't demand a
     * password.
     */
    protected function getCurrentPasswordFormComponent(): Component
    {
        // Built from scratch rather than chained onto the parent's
        // version. The parent applies BOTH `->required()` and
        // `->currentPassword()` unconditionally and switches them off
        // only by hiding the field — Filament skips validation for
        // hidden components. Making the field permanently visible would
        // therefore make both rules permanently active, so saving a name
        // or avatar would fail asking for the account password. Neither
        // can be undone by chaining either: `required()` would be
        // overwritten but `rule('current_password:…')` is additive, so
        // the inherited copy would still run against an empty value.
        $isChangingCredentials = fn (Get $get): bool => filled($get('password'))
            || ($get('email') !== $this->getUser()->getAttributeValue('email'));

        return TextInput::make('currentPassword')
            ->label('Current password')
            ->password()
            ->autocomplete('current-password')
            ->revealable(filament()->arePasswordsRevealable())
            ->currentPassword(condition: $isChangingCredentials, guard: Filament::getAuthGuard())
            ->required($isChangingCredentials)
            ->helperText('Required only when changing your password or email address.')
            ->dehydrated(false);
    }

    /**
     * Also always visible, for the same reason — the confirmation is the
     * third step of a three-step block, and Filament hides it until the
     * new password is non-empty. Still only enforced once a new password
     * has been typed.
     */
    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Confirm new password')
            ->visible(true)
            ->required(fn (Get $get): bool => filled($get('password')));
    }

    /**
     * Narrower return type than the sibling `get*FormComponent()` methods
     * (which the parent declares as `Component`) so the caller can chain
     * FileUpload/Field methods like helperText() without a type error.
     */
    protected function getAvatarFormComponent(): FileUpload
    {
        /** @var FileUpload $upload */
        $upload = $this->resolveUploadPreviewsSameOrigin(
            FileUpload::make('avatar')
                ->label('Profile photo')
                ->avatar()
                ->disk('public')
                ->directory('avatars')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(2048),
        );

        return $upload;
    }

    /**
     * Mirrors PlatformProfileController::uploadAvatar()'s explicit
     * deletion of the previous file on replacement, so orphaned avatar
     * files don't pile up on the public disk.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $previousAvatar = $record->getOriginal('avatar');

        if (
            array_key_exists('avatar', $data)
            && $data['avatar'] !== $previousAvatar
            && filled($previousAvatar)
        ) {
            Storage::disk('public')->delete($previousAvatar);
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
