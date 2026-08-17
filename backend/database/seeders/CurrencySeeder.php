<?php

namespace Database\Seeders;

use App\Domain\Localization\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Real ISO-4217 currencies relevant to BiasharaMax's primary markets,
 * not an exhaustive world list. TZS is the platform base currency
 * (matches the existing `subscription_plans`/`payment_transactions`
 * default of TZS elsewhere in the codebase).
 */
class CurrencySeeder extends Seeder
{
    private const CATALOG = [
        ['code' => 'TZS', 'name' => 'Tanzanian Shilling', 'symbol' => 'TSh', 'is_base' => true, 'exchange_rate_to_base' => 1],
        ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'is_base' => false, 'exchange_rate_to_base' => 0.062],
        ['code' => 'UGX', 'name' => 'Ugandan Shilling', 'symbol' => 'USh', 'is_base' => false, 'exchange_rate_to_base' => 0.69],
        ['code' => 'RWF', 'name' => 'Rwandan Franc', 'symbol' => 'FRw', 'is_base' => false, 'exchange_rate_to_base' => 1.85],
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_base' => false, 'exchange_rate_to_base' => 2600],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_base' => false, 'exchange_rate_to_base' => 2800],
        ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'is_base' => false, 'exchange_rate_to_base' => 3300],
        ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'is_base' => false, 'exchange_rate_to_base' => 140],
        ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'is_base' => false, 'exchange_rate_to_base' => 1.7],
        ['code' => 'GHS', 'name' => 'Ghanaian Cedi', 'symbol' => '₵', 'is_base' => false, 'exchange_rate_to_base' => 170],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'is_base' => $currency['is_base'],
                    'exchange_rate_to_base' => $currency['exchange_rate_to_base'],
                    'rate_updated_at' => now(),
                ],
            );
        }
    }
}
