<?php

namespace App\Domain\Settings\Services;

use App\Domain\Platform\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * One canonical schema (group => [key => default]) backed by the
 * existing `platform_settings` key-value store (built in the Platform
 * Operations sprint) — no new settings table. File-type settings
 * (logos, favicon) are stored as public-disk paths.
 */
class PlatformSettingsService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public const SCHEMA = [
        'general' => [
            'platform_name' => 'BiasharaMax',
            'platform_logo' => null,
            'favicon' => null,
            'company_name' => null,
            'company_address' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'support_email' => null,
            'maintenance_mode' => false,
            'maintenance_message' => 'We are performing scheduled maintenance. Please check back soon.',
            'timezone' => 'Africa/Dar_es_Salaam',
            'date_format' => 'd M Y',
            'time_format' => 'H:i',
            'default_language' => 'en',
            'default_currency' => 'TZS',
            'default_country' => 'TZ',
        ],
        'branding' => [
            'logo_light' => null,
            'logo_dark' => null,
            'login_logo' => null,
            'email_logo' => null,
            'watermark' => null,
            'app_icon' => null,
            'browser_theme_color' => '#4F46E5',
            'primary_color' => '#4F46E5',
            'secondary_color' => '#6366F1',
            'accent_color' => '#F59E0B',
        ],
        'localization' => [
            'default_locale' => 'en',
            'google_translate_enabled' => false,
        ],
        'email' => [
            'default_provider' => 'smtp',
            'queue_enabled' => true,
        ],
        'sms' => [
            'default_provider' => null,
        ],
        'payment' => [
            'global_currency' => 'TZS',
            'platform_commission_percent' => 0,
            'invoice_prefix' => 'INV-',
            'receipt_prefix' => 'RCT-',
            'payment_timeout_minutes' => 30,
            'default_gateway' => null,
            'refund_window_days' => 14,
        ],
        'security' => [
            'password_min_length' => 8,
            'session_timeout_minutes' => 120,
            'max_login_attempts' => 5,
            'two_factor_required_for_staff' => false,
            'ip_restriction_enabled' => false,
            'captcha_enabled' => false,
        ],
        'storage' => [
            'default_disk' => 'local',
            'storage_limit_mb' => null,
            'max_file_size_mb' => 10,
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'pdf', 'csv', 'xlsx'],
        ],
        'business_defaults' => [
            'default_subscription_plan_id' => null,
            'default_business_type_id' => null,
            'default_website_template_id' => null,
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allGrouped(): array
    {
        $grouped = [];

        foreach (self::SCHEMA as $group => $keys) {
            foreach ($keys as $key => $default) {
                $grouped[$group][$key] = PlatformSetting::get("{$group}.{$key}", $default);
            }
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateGroup(string $group, array $values, ?string $updatedBy = null): void
    {
        if (! array_key_exists($group, self::SCHEMA)) {
            throw new \InvalidArgumentException("Unknown settings group: {$group}");
        }

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::SCHEMA[$group])) {
                continue;
            }

            PlatformSetting::set("{$group}.{$key}", $value, $updatedBy);
        }
    }

    public function storeUploadedFile(UploadedFile $file, string $directory = 'branding'): string
    {
        $path = $file->store($directory, 'public');

        return Storage::url($path);
    }
}
