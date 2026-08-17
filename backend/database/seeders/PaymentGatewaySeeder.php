<?php

namespace Database\Seeders;

use App\Domain\Finance\Models\PaymentGateway;
use Illuminate\Database\Seeder;

/**
 * Registers the full gateway catalog the Finance module supports.
 * Every row is created disabled with no credentials — a real,
 * selectable gateway that simply isn't configured yet. SuperAdmin
 * enables one by supplying real API keys via the Payment Gateways UI.
 */
class PaymentGatewaySeeder extends Seeder
{
    private const CATALOG = [
        ['name' => 'Stripe', 'slug' => 'stripe', 'provider' => PaymentGateway::PROVIDER_STRIPE, 'currencies' => ['USD', 'EUR', 'GBP'], 'countries' => ['US', 'GB', 'EU']],
        ['name' => 'Snippe', 'slug' => 'snippe', 'provider' => PaymentGateway::PROVIDER_SNIPPE, 'currencies' => ['TZS'], 'countries' => ['TZ']],
        ['name' => 'Pesapal', 'slug' => 'pesapal', 'provider' => PaymentGateway::PROVIDER_PESAPAL, 'currencies' => ['TZS', 'KES', 'UGX'], 'countries' => ['TZ', 'KE', 'UG']],
        ['name' => 'Flutterwave', 'slug' => 'flutterwave', 'provider' => PaymentGateway::PROVIDER_FLUTTERWAVE, 'currencies' => ['TZS', 'KES', 'NGN', 'GHS', 'USD'], 'countries' => ['TZ', 'KE', 'NG', 'GH']],
        ['name' => 'PayPal', 'slug' => 'paypal', 'provider' => PaymentGateway::PROVIDER_PAYPAL, 'currencies' => ['USD', 'EUR', 'GBP'], 'countries' => null],
        ['name' => 'Bank Transfer', 'slug' => 'bank-transfer', 'provider' => PaymentGateway::PROVIDER_BANK_TRANSFER, 'currencies' => ['TZS', 'USD'], 'countries' => null],
        ['name' => 'M-Pesa', 'slug' => 'mpesa', 'provider' => PaymentGateway::PROVIDER_MPESA, 'currencies' => ['TZS', 'KES'], 'countries' => ['TZ', 'KE']],
        ['name' => 'Airtel Money', 'slug' => 'airtel-money', 'provider' => PaymentGateway::PROVIDER_AIRTEL_MONEY, 'currencies' => ['TZS'], 'countries' => ['TZ']],
        ['name' => 'Tigo Pesa', 'slug' => 'tigo-pesa', 'provider' => PaymentGateway::PROVIDER_TIGO_PESA, 'currencies' => ['TZS'], 'countries' => ['TZ']],
        ['name' => 'HaloPesa', 'slug' => 'halopesa', 'provider' => PaymentGateway::PROVIDER_HALOPESA, 'currencies' => ['TZS'], 'countries' => ['TZ']],
        ['name' => 'Mixx by Yas', 'slug' => 'mixx-by-yas', 'provider' => PaymentGateway::PROVIDER_MIXX_BY_YAS, 'currencies' => ['TZS'], 'countries' => ['TZ']],
        ['name' => 'Custom Gateway', 'slug' => 'custom-gateway', 'provider' => PaymentGateway::PROVIDER_CUSTOM, 'currencies' => null, 'countries' => null],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $index => $gateway) {
            PaymentGateway::query()->updateOrCreate(
                ['slug' => $gateway['slug']],
                [
                    'name' => $gateway['name'],
                    'provider' => $gateway['provider'],
                    'is_enabled' => false,
                    'mode' => PaymentGateway::MODE_SANDBOX,
                    'supported_currencies' => $gateway['currencies'],
                    'supported_countries' => $gateway['countries'],
                    'sort_order' => $index,
                ],
            );
        }
    }
}
