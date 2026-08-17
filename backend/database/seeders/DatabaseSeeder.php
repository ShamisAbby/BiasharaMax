<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Order matters: PlatformRoleSeeder and RoleTemplateSeeder
            // both resolve permission slugs to IDs, so the permissions
            // have to exist first.
            PermissionSeeder::class,
            PlatformRoleSeeder::class,
            RoleTemplateSeeder::class,
            BusinessTypeSeeder::class,
            WebsiteTemplateSeeder::class,
            // After WebsiteTemplateSeeder: this one is business-type
            // agnostic and not a default, so it must not run before the
            // per-type defaults are in place.
            LuxeWebsiteTemplateSeeder::class,
            SubscriptionPlanSeeder::class,
            PaymentGatewaySeeder::class,
            NotificationChannelSeeder::class,
            // After the channels: templates reference channel types, so
            // seeding them first would describe delivery routes that do
            // not exist yet.
            NotificationTemplateSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
            IntegrationSeeder::class,
        ]);
    }
}
