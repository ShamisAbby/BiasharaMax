<?php

namespace App\Domain\Platform\Filament\Pages;

use App\Domain\Business\Models\BusinessType;
use App\Domain\Platform\Filament\Concerns\ResolvesUploadPreviewsSameOrigin;
use App\Domain\Platform\Services\EnvEditorService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Platform-wide settings, replicating
 * App\Domain\Platform\Http\Controllers\PlatformSettingsController and
 * EnvSettingsController exactly.
 *
 * Two different stores sit behind one form, which is why the save
 * action fans out rather than writing everything one way:
 *
 *  - The first nine tabs are the DB-backed groups in
 *    PlatformSettingsService::SCHEMA, persisted to the existing
 *    `platform_settings` key-value table via that service. Its
 *    updateGroup() silently drops any key not in the schema, so the
 *    field names below must match the schema keys exactly.
 *  - The last three tabs edit the `.env` file through
 *    EnvEditorService. Those are gated by ALLOWED_ENV_KEYS below, which
 *    mirrors EnvSettingsController::ALLOWED — that constant is private,
 *    so it is repeated here rather than shared. Keep the two in sync;
 *    anything absent from the list is never written.
 *
 * Currencies and Tax Rates were tabs on the old screen too, but this
 * panel already has full CRUD resources for both (under Finance), so
 * they are not duplicated here.
 */
class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use ResolvesUploadPreviewsSameOrigin;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = -1;

    protected static ?string $title = 'System Settings';

    protected string $view = 'filament.platform.pages.system-settings';

    /**
     * Mirrors EnvSettingsController::ALLOWED. Nothing outside these
     * lists is ever written to .env. (EnvEditorService additionally
     * refuses APP_KEY outright, whatever is passed to it.)
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ENV_KEYS = [
        'app' => ['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL'],
        'mail' => [
            'MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT',
            'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
        ],
        'database' => [
            'DB_CONNECTION', 'DB_HOST', 'DB_PORT',
            'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        ],
    ];

    /**
     * Allow-listed env keys rendered as a Toggle rather than a text
     * field, and so needing string ⇄ bool conversion in both directions.
     *
     * @var list<string>
     */
    private const BOOLEAN_ENV_KEYS = ['APP_DEBUG'];

    /**
     * Settings keys holding an uploaded image. Stored as a root-relative
     * `/storage/...` URL by the old controller's uploadFile(); Filament's
     * FileUpload works in disk-relative paths, so these are translated
     * both ways in mount()/save() to keep one storage format across both
     * UIs. See urlToDiskPath()/diskPathToUrl() for why the exact string
     * form matters.
     *
     * @var array<string, list<string>>
     */
    private const IMAGE_KEYS = [
        'general' => ['platform_logo', 'favicon'],
        'branding' => ['logo_light', 'logo_dark', 'login_logo', 'email_logo', 'watermark', 'app_icon'],
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::guard('platform')->user()?->hasPlatformPermission('platform_settings.view') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = app(PlatformSettingsService::class)->allGrouped();
        $env = app(EnvEditorService::class);

        foreach (self::IMAGE_KEYS as $group => $keys) {
            foreach ($keys as $key) {
                $settings[$group][$key] = $this->urlToDiskPath($settings[$group][$key] ?? null);
            }
        }

        $this->form->fill([
            ...$settings,
            'app' => $this->readEnvGroup($env, 'app'),
            'mail' => $this->readEnvGroup($env, 'mail'),
            'database' => $this->readEnvGroup($env, 'database'),
        ]);
    }

    /**
     * EnvEditorService::getGroup() returns every variable sharing the
     * group's prefix, which for `app` includes APP_KEY — and Schema::fill()
     * assigns the array verbatim to the public `$data` property, so
     * whatever goes in is serialised into the Livewire snapshot in the
     * page HTML. Restricting the read to the same allow-list that governs
     * writes keeps the application key (and other unrelated APP_* vars)
     * out of the response entirely.
     *
     * Values arrive as raw strings, so anything bound to a Toggle is
     * converted here: `boolval('false')` is TRUE, which would render the
     * toggle ON for `APP_DEBUG=false` and then write `true` back on save.
     *
     * @return array<string, string|bool>
     */
    private function readEnvGroup(EnvEditorService $env, string $group): array
    {
        $prefix = ($group === 'database' ? 'DB' : Str::upper($group)).'_';
        $allowed = self::ALLOWED_ENV_KEYS[$group];
        $values = [];

        foreach ($env->getGroup($group) as $key => $value) {
            if (! in_array($prefix.Str::upper($key), $allowed, true)) {
                continue;
            }

            $values[$key] = in_array($prefix.Str::upper($key), self::BOOLEAN_ENV_KEYS, true)
                ? filter_var($value, FILTER_VALIDATE_BOOL)
                : $value;
        }

        return $values;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        $this->generalTab(),
                        $this->brandingTab(),
                        $this->localizationTab(),
                        $this->emailTab(),
                        $this->smsTab(),
                        $this->paymentTab(),
                        $this->securityTab(),
                        $this->storageTab(),
                        $this->businessDefaultsTab(),
                        $this->applicationTab(),
                        $this->mailServerTab(),
                        $this->databaseTab(),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->icon(Heroicon::OutlinedCheck)
                ->action('save')
                ->visible(fn (): bool => $this->canManageSettings()),
        ];
    }

    public function save(): void
    {
        abort_unless($this->canManageSettings(), 403);

        $state = $this->form->getState();
        $settings = app(PlatformSettingsService::class);
        $userId = Auth::guard('platform')->id();

        foreach (array_keys(PlatformSettingsService::SCHEMA) as $group) {
            $values = $state[$group] ?? [];

            foreach (self::IMAGE_KEYS[$group] ?? [] as $key) {
                $values[$key] = $this->diskPathToUrl($values[$key] ?? null);
            }

            $settings->updateGroup($group, $values, $userId);
        }

        // Env values are flattened back to their prefixed UPPER_CASE keys
        // and filtered through the allow-list, exactly as
        // EnvSettingsController::update() does before handing them over.
        $envValues = [];

        foreach (self::ALLOWED_ENV_KEYS as $group => $allowed) {
            $prefix = ($group === 'database' ? 'DB' : Str::upper($group)).'_';

            foreach ($state[$group] ?? [] as $key => $value) {
                $envKey = $prefix.Str::upper($key);

                if (! in_array($envKey, $allowed, true)) {
                    continue;
                }

                // Same normalisation as EnvSettingsController::update():
                // booleans (however they arrive) are written as the
                // literal strings the .env file expects.
                $envValues[$envKey] = is_bool($value) || in_array($value, ['true', 'false', '1', '0', 'on', 'off'], true)
                    ? (in_array($value, [true, 'true', '1', 'on'], true) ? 'true' : 'false')
                    : (string) ($value ?? '');
            }
        }

        if ($envValues !== []) {
            app(EnvEditorService::class)->set($envValues);
        }

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    /** Public because the page's Blade view reads it too. */
    public function canManageSettings(): bool
    {
        return Auth::guard('platform')->user()?->hasPlatformPermission('platform_settings.manage') ?? false;
    }

    /**
     * `/storage/branding/x.png` → `branding/x.png`, which is the
     * disk-relative form FileUpload expects.
     *
     * Getting this exactly right matters: FileUpload checks the path
     * exists on its disk and silently drops the value if not, so a
     * mis-translated path would come back as an empty field and then be
     * saved as null — quietly destroying a logo uploaded from the old
     * screen. Both the root-relative form and an absolute
     * `https://host/storage/...` are handled, since APP_URL may have
     * changed since the value was written. Anything else (an external
     * CDN URL, say) is left untouched.
     */
    private function urlToDiskPath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        foreach (['/storage/', Storage::disk('public')->url('')] as $prefix) {
            if ($prefix !== '' && str_starts_with($value, $prefix)) {
                return Str::after($value, $prefix);
            }
        }

        return $value;
    }

    /**
     * The inverse. Deliberately the `Storage::url()` FACADE, not
     * `Storage::disk('public')->url()`: the old
     * PlatformSettingsService::storeUploadedFile() uses the facade,
     * which resolves the DEFAULT disk (`local`, which has no `url`
     * configured) and so yields the root-relative `/storage/branding/x.png`.
     * The `public` disk does define a url, and would produce an absolute
     * `http://host/storage/...` instead — a different string, with the
     * hostname baked in. Matching the facade keeps one storage format
     * across both UIs.
     */
    private function diskPathToUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Str::startsWith($value, ['http://', 'https://', '/'])
            ? $value
            : Storage::url($value);
    }

    /**
     * All eight branding images share the same disk, directory and
     * limits (2 MB, matching PlatformSettingsController::uploadFile()'s
     * `max:2048`), and all need the same-origin preview fix — so they
     * are built in one place rather than repeated per field.
     */
    private function imageUpload(string $name, string $label): FileUpload
    {
        /** @var FileUpload $upload */
        $upload = $this->resolveUploadPreviewsSameOrigin(
            FileUpload::make($name)
                ->label($label)
                ->image()
                ->disk('public')
                ->directory('branding')
                ->maxSize(2048),
        );

        return $upload;
    }

    private function generalTab(): Tab
    {
        return Tab::make('General')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->schema([
                TextInput::make('general.platform_name')->label('Platform name')->required(),
                $this->imageUpload('general.platform_logo', 'Platform logo'),
                $this->imageUpload('general.favicon', 'Favicon'),
                TextInput::make('general.company_name')->label('Company name'),
                Textarea::make('general.company_address')->label('Company address')->rows(2),
                TextInput::make('general.phone')->label('Phone')->tel(),
                TextInput::make('general.email')->label('Email')->email(),
                TextInput::make('general.website')->label('Website')->url(),
                TextInput::make('general.support_email')->label('Support email')->email(),
                Toggle::make('general.maintenance_mode')->label('Maintenance mode'),
                Textarea::make('general.maintenance_message')->label('Maintenance message')->rows(2),
                Select::make('general.timezone')->label('Timezone')
                    ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable(),
                TextInput::make('general.date_format')->label('Date format')
                    ->helperText('PHP date() format, e.g. d M Y'),
                TextInput::make('general.time_format')->label('Time format')
                    ->helperText('PHP date() format, e.g. H:i'),
                TextInput::make('general.default_language')->label('Default language')->maxLength(5),
                TextInput::make('general.default_currency')->label('Default currency')->maxLength(3),
                TextInput::make('general.default_country')->label('Default country')->maxLength(2),
            ])
            ->columns(2);
    }

    private function brandingTab(): Tab
    {
        return Tab::make('Branding')
            ->icon(Heroicon::OutlinedSwatch)
            ->schema([
                $this->imageUpload('branding.logo_light', 'Logo (light)'),
                $this->imageUpload('branding.logo_dark', 'Logo (dark)'),
                $this->imageUpload('branding.login_logo', 'Login logo'),
                $this->imageUpload('branding.email_logo', 'Email logo'),
                $this->imageUpload('branding.watermark', 'Watermark'),
                $this->imageUpload('branding.app_icon', 'App icon'),
                ColorPicker::make('branding.primary_color')->label('Primary color'),
                ColorPicker::make('branding.secondary_color')->label('Secondary color'),
                ColorPicker::make('branding.accent_color')->label('Accent color'),
                ColorPicker::make('branding.browser_theme_color')->label('Browser theme color'),
            ])
            ->columns(2);
    }

    private function localizationTab(): Tab
    {
        return Tab::make('Localization')
            ->icon(Heroicon::OutlinedLanguage)
            ->schema([
                TextInput::make('localization.default_locale')->label('Default locale')->maxLength(5),
                Toggle::make('localization.google_translate_enabled')->label('Google Translate enabled'),
            ])
            ->columns(2);
    }

    private function emailTab(): Tab
    {
        return Tab::make('Email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->schema([
                TextInput::make('email.default_provider')->label('Default provider'),
                Toggle::make('email.queue_enabled')->label('Queue outgoing email'),
            ])
            ->columns(2);
    }

    private function smsTab(): Tab
    {
        return Tab::make('SMS')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->schema([
                TextInput::make('sms.default_provider')->label('Default provider'),
            ])
            ->columns(2);
    }

    private function paymentTab(): Tab
    {
        return Tab::make('Payment')
            ->icon(Heroicon::OutlinedBanknotes)
            ->schema([
                TextInput::make('payment.global_currency')->label('Global currency')->maxLength(3),
                TextInput::make('payment.platform_commission_percent')->label('Platform commission (%)')
                    ->numeric()->minValue(0)->maxValue(100),
                TextInput::make('payment.invoice_prefix')->label('Invoice prefix'),
                TextInput::make('payment.receipt_prefix')->label('Receipt prefix'),
                TextInput::make('payment.payment_timeout_minutes')->label('Payment timeout (minutes)')
                    ->numeric()->minValue(1),
                TextInput::make('payment.default_gateway')->label('Default gateway'),
                TextInput::make('payment.refund_window_days')->label('Refund window (days)')
                    ->numeric()->minValue(0),
            ])
            ->columns(2);
    }

    private function securityTab(): Tab
    {
        return Tab::make('Security')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->schema([
                TextInput::make('security.password_min_length')->label('Minimum password length')
                    ->numeric()->minValue(6),
                TextInput::make('security.session_timeout_minutes')->label('Session timeout (minutes)')
                    ->numeric()->minValue(1),
                TextInput::make('security.max_login_attempts')->label('Max login attempts')
                    ->numeric()->minValue(1),
                Toggle::make('security.two_factor_required_for_staff')->label('Require 2FA for staff'),
                Toggle::make('security.ip_restriction_enabled')->label('IP restriction enabled'),
                Toggle::make('security.captcha_enabled')->label('CAPTCHA enabled'),
            ])
            ->columns(2);
    }

    private function storageTab(): Tab
    {
        return Tab::make('Storage')
            ->icon(Heroicon::OutlinedCircleStack)
            ->schema([
                TextInput::make('storage.default_disk')->label('Default disk'),
                TextInput::make('storage.storage_limit_mb')->label('Storage limit (MB)')
                    ->numeric()->minValue(0),
                TextInput::make('storage.max_file_size_mb')->label('Max file size (MB)')
                    ->numeric()->minValue(1),
                Select::make('storage.allowed_file_types')->label('Allowed file types')
                    ->multiple()
                    ->options(array_combine(
                        $types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'csv', 'xlsx', 'xls', 'doc', 'docx', 'zip'],
                        $types,
                    ))
                    ->helperText('Extensions accepted for uploads across the platform.'),
            ])
            ->columns(2);
    }

    private function businessDefaultsTab(): Tab
    {
        return Tab::make('Business Defaults')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->schema([
                Select::make('business_defaults.default_subscription_plan_id')->label('Default subscription plan')
                    ->options(fn (): array => SubscriptionPlan::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->searchable(),
                Select::make('business_defaults.default_business_type_id')->label('Default business type')
                    ->options(fn (): array => BusinessType::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Select::make('business_defaults.default_website_template_id')->label('Default website template')
                    ->options(fn (): array => WebsiteTemplate::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->columns(2);
    }

    private function applicationTab(): Tab
    {
        return Tab::make('Application')
            ->icon(Heroicon::OutlinedServerStack)
            ->schema([
                TextInput::make('app.name')->label('Application name')->placeholder('BiasharaMax'),
                Select::make('app.env')->label('Environment')
                    ->options(['local' => 'local', 'staging' => 'staging', 'production' => 'production']),
                Toggle::make('app.debug')->label('Debug mode')
                    ->helperText('Turn off in production.'),
                TextInput::make('app.url')->label('Application URL')->url()->placeholder('http://localhost:8000'),
            ])
            ->columns(2);
    }

    private function mailServerTab(): Tab
    {
        return Tab::make('Mail Server')
            ->icon(Heroicon::OutlinedAtSymbol)
            ->schema([
                Select::make('mail.mailer')->label('Mailer')
                    ->options(array_combine(
                        $mailers = ['smtp', 'log', 'array', 'mailgun', 'ses', 'postmark'],
                        $mailers,
                    )),
                Select::make('mail.scheme')->label('Scheme')
                    ->options(['smtp' => 'smtp', 'smtps' => 'smtps']),
                TextInput::make('mail.host')->label('SMTP host')->placeholder('smtp.hostinger.com'),
                TextInput::make('mail.port')->label('SMTP port')->numeric()->placeholder('465'),
                TextInput::make('mail.username')->label('Username')->placeholder('you@example.com'),
                TextInput::make('mail.password')->label('Password')->password()->revealable(),
                TextInput::make('mail.from_address')->label('From address')->email()->placeholder('noreply@example.com'),
                TextInput::make('mail.from_name')->label('From name')->placeholder('BiasharaMax'),
            ])
            ->columns(2);
    }

    private function databaseTab(): Tab
    {
        return Tab::make('Database')
            ->icon(Heroicon::OutlinedCircleStack)
            ->schema([
                Select::make('database.connection')->label('Driver')
                    ->options(array_combine(
                        $drivers = ['pgsql', 'mysql', 'sqlite', 'sqlsrv'],
                        $drivers,
                    )),
                TextInput::make('database.host')->label('Host')->placeholder('127.0.0.1'),
                TextInput::make('database.port')->label('Port')->numeric()->placeholder('5432'),
                TextInput::make('database.database')->label('Database name'),
                TextInput::make('database.username')->label('Username'),
                TextInput::make('database.password')->label('Password')->password()->revealable(),
            ])
            ->columns(2);
    }
}
