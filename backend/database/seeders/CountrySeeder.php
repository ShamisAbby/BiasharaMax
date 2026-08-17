<?php

namespace Database\Seeders;

use App\Domain\Localization\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Real ISO-3166 country codes — scoped to BiasharaMax's primary East
 * African markets plus a handful of major global ones, not the full
 * 195-country list. Regions/cities are deliberately not modeled (see
 * the `countries` migration docblock).
 */
class CountrySeeder extends Seeder
{
    private const CATALOG = [
        ['code' => 'TZ', 'name' => 'Tanzania', 'default_currency_code' => 'TZS', 'phone_code' => '+255'],
        ['code' => 'KE', 'name' => 'Kenya', 'default_currency_code' => 'KES', 'phone_code' => '+254'],
        ['code' => 'UG', 'name' => 'Uganda', 'default_currency_code' => 'UGX', 'phone_code' => '+256'],
        ['code' => 'RW', 'name' => 'Rwanda', 'default_currency_code' => 'RWF', 'phone_code' => '+250'],
        ['code' => 'ZA', 'name' => 'South Africa', 'default_currency_code' => 'ZAR', 'phone_code' => '+27'],
        ['code' => 'NG', 'name' => 'Nigeria', 'default_currency_code' => 'NGN', 'phone_code' => '+234'],
        ['code' => 'GH', 'name' => 'Ghana', 'default_currency_code' => 'GHS', 'phone_code' => '+233'],
        ['code' => 'US', 'name' => 'United States', 'default_currency_code' => 'USD', 'phone_code' => '+1'],
        ['code' => 'GB', 'name' => 'United Kingdom', 'default_currency_code' => 'GBP', 'phone_code' => '+44'],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $country) {
            Country::query()->updateOrCreate(['code' => $country['code']], $country);
        }
    }
}
