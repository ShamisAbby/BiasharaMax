<?php

namespace Database\Seeders;

use App\Domain\Business\Models\BusinessType;
use Illuminate\Database\Seeder;

/**
 * Slugs match the legacy hardcoded list the public registration form
 * used before it was wired to this admin-managed table, so existing
 * `businesses.business_type` string values keep resolving correctly.
 */
class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Retail Shop', 'slug' => 'retail', 'icon' => 'building-storefront', 'color' => '#4F46E5'],
            ['name' => 'Supermarket', 'slug' => 'supermarket', 'icon' => 'shopping-cart', 'color' => '#0EA5E9'],
            ['name' => 'Restaurant', 'slug' => 'restaurant', 'icon' => 'cake', 'color' => '#F97316'],
            ['name' => 'Pharmacy', 'slug' => 'pharmacy', 'icon' => 'beaker', 'color' => '#10B981'],
            ['name' => 'Hardware Store', 'slug' => 'hardware', 'icon' => 'wrench', 'color' => '#6B7280'],
            ['name' => 'Electronics Shop', 'slug' => 'electronics', 'icon' => 'cpu-chip', 'color' => '#8B5CF6'],
            ['name' => 'Fashion Store', 'slug' => 'fashion', 'icon' => 'sparkles', 'color' => '#EC4899'],
            ['name' => 'Beauty Salon', 'slug' => 'beauty', 'icon' => 'scissors', 'color' => '#D946EF'],
            ['name' => 'Wholesale Business', 'slug' => 'wholesale', 'icon' => 'truck', 'color' => '#0891B2'],
            ['name' => 'Service Business', 'slug' => 'service', 'icon' => 'wrench-screwdriver', 'color' => '#F59E0B'],
            ['name' => 'Other', 'slug' => 'other', 'icon' => 'ellipsis-horizontal', 'color' => '#64748B'],
        ];

        foreach ($types as $index => $type) {
            BusinessType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'icon' => $type['icon'],
                    'color' => $type['color'],
                    'default_currency' => 'TZS',
                    'default_tax_rate' => 18.00,
                    'inventory_enabled' => true,
                    'status' => BusinessType::STATUS_ACTIVE,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
