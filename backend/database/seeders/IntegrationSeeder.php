<?php

namespace Database\Seeders;

use App\Domain\Integrations\Models\Integration;
use Illuminate\Database\Seeder;

/**
 * Registers the integration catalog from the System Management spec —
 * every row starts disabled with no credentials, same "real but
 * unconfigured" pattern as payment_gateways/notification_channels.
 * Payment gateways, SMS/email channels, and outbound webhooks already
 * have their own dedicated tables/catalogs from earlier sprints and
 * are deliberately NOT duplicated here.
 */
class IntegrationSeeder extends Seeder
{
    private const CATALOG = [
        ['name' => 'Google Maps', 'provider' => 'google_maps', 'category' => Integration::CATEGORY_MAPS],
        ['name' => 'Google Analytics', 'provider' => 'google_analytics', 'category' => Integration::CATEGORY_ANALYTICS],
        ['name' => 'Google Translate', 'provider' => 'google_translate', 'category' => Integration::CATEGORY_ANALYTICS],
        ['name' => 'Google OAuth', 'provider' => 'google_oauth', 'category' => Integration::CATEGORY_OAUTH],
        ['name' => 'Microsoft OAuth', 'provider' => 'microsoft_oauth', 'category' => Integration::CATEGORY_OAUTH],
        ['name' => 'Facebook Login', 'provider' => 'facebook_login', 'category' => Integration::CATEGORY_SOCIAL_LOGIN],
        ['name' => 'Apple Login', 'provider' => 'apple_login', 'category' => Integration::CATEGORY_SOCIAL_LOGIN],
        ['name' => 'OpenAI', 'provider' => 'openai', 'category' => Integration::CATEGORY_AI],
        ['name' => 'Claude AI', 'provider' => 'claude', 'category' => Integration::CATEGORY_AI],
        ['name' => 'Gemini', 'provider' => 'gemini', 'category' => Integration::CATEGORY_AI],
        ['name' => 'WhatsApp Business API', 'provider' => 'whatsapp_business', 'category' => Integration::CATEGORY_COMMUNICATION],
        ['name' => 'Slack', 'provider' => 'slack', 'category' => Integration::CATEGORY_AUTOMATION],
        ['name' => 'Discord', 'provider' => 'discord', 'category' => Integration::CATEGORY_AUTOMATION],
        ['name' => 'Zapier', 'provider' => 'zapier', 'category' => Integration::CATEGORY_AUTOMATION],
        ['name' => 'Make.com', 'provider' => 'make', 'category' => Integration::CATEGORY_AUTOMATION],
        ['name' => 'FTP', 'provider' => 'ftp', 'category' => Integration::CATEGORY_STORAGE],
        ['name' => 'Google Drive', 'provider' => 'google_drive', 'category' => Integration::CATEGORY_STORAGE],
        ['name' => 'Dropbox', 'provider' => 'dropbox', 'category' => Integration::CATEGORY_STORAGE],
        ['name' => 'OneDrive', 'provider' => 'onedrive', 'category' => Integration::CATEGORY_STORAGE],
        ['name' => 'Amazon S3', 'provider' => 'amazon_s3', 'category' => Integration::CATEGORY_STORAGE],
        ['name' => 'Cloudflare R2', 'provider' => 'cloudflare_r2', 'category' => Integration::CATEGORY_STORAGE],
        ['name' => 'Custom Integration', 'provider' => 'custom', 'category' => Integration::CATEGORY_CUSTOM],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $index => $integration) {
            Integration::query()->updateOrCreate(
                ['provider' => $integration['provider']],
                [
                    'name' => $integration['name'],
                    'slug' => str_replace('_', '-', $integration['provider']),
                    'category' => $integration['category'],
                    'is_enabled' => false,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
