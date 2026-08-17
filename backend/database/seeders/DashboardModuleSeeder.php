<?php

namespace Database\Seeders;

use App\Domain\ModuleManagement\Models\Module;
use App\Domain\ModuleManagement\Support\DashboardModule;
use Illuminate\Database\Seeder;

/**
 * Registers the ten vendor dashboard sections as modules.
 *
 * `updateOrCreate` on the slug, and deliberately narrow: it refreshes the
 * name, description and ordering but never touches `status`. Re-running
 * this seeder after a Super Admin has switched Payroll off must not switch
 * it back on — a seeder that silently undoes an operator's decision is
 * worse than one that does nothing.
 */
class DashboardModuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DashboardModule::catalogue() as $slug => $meta) {
            $existing = Module::query()->where('slug', $slug)->first();

            $attributes = [
                'name' => $meta['name'],
                'description' => $meta['description'],
                'icon' => $meta['icon'],
                'category' => $meta['category'],
                'sort_order' => $meta['sort_order'],
            ];

            // Only set the status when creating the row, so an existing
            // decision survives a re-seed.
            if ($existing === null) {
                $attributes['status'] = Module::STATUS_ACTIVE;
                $attributes['visibility'] = Module::VISIBILITY_PUBLIC;
            }

            Module::query()->updateOrCreate(['slug' => $slug], $attributes);
        }
    }
}
