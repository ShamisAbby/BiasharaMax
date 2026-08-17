<?php

namespace Database\Seeders;

use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\RoleTemplate;
use App\Domain\RBAC\Services\PlatformRoleProvisioningService;
use App\Domain\RBAC\Services\RoleProvisioningService;
use Illuminate\Database\Seeder;

/**
 * Publishes a reusable starter template for every role in both
 * catalogues, so "create role from template" has something to offer
 * instead of an empty dropdown.
 *
 * Templates are a starting point, not a live link: applying one copies
 * its permissions onto a role once, and the two drift apart freely
 * afterwards. That is the difference from the provisioning services —
 * those own their system roles and re-sync them on every run.
 *
 * The permission lists are read from the two services rather than
 * restated, so a template can never disagree with the role it is named
 * after. Both scopes are covered: `platform` templates for staff roles,
 * `tenant` templates for the roles a business gets.
 *
 * Depends on PermissionSeeder having run first.
 */
class RoleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlatformTemplates();
        $this->seedTenantTemplates();
    }

    private function seedPlatformTemplates(): void
    {
        $permissionIdsBySlug = Permission::query()
            ->where('scope', Permission::SCOPE_PLATFORM)
            ->pluck('id', 'slug');

        $service = app(PlatformRoleProvisioningService::class);

        foreach (PlatformRoleProvisioningService::catalogue() as $slug => $definition) {
            $template = RoleTemplate::query()->updateOrCreate(
                ['slug' => 'platform-'.$slug],
                [
                    'name' => $definition['name'],
                    'scope' => 'platform',
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );

            $template->permissions()->sync(
                $service->resolvePermissionIds($slug, $definition['permissions'], $permissionIdsBySlug),
            );
        }
    }

    private function seedTenantTemplates(): void
    {
        $tenantPermissions = Permission::query()
            ->where('scope', Permission::SCOPE_TENANT)
            ->get(['id', 'slug', 'action']);

        $idsBySlug = $tenantPermissions->pluck('id', 'slug');
        $viewIds = $tenantPermissions->where('action', 'view')->pluck('id');

        foreach (RoleProvisioningService::catalogue() as $slug => $definition) {
            $template = RoleTemplate::query()->updateOrCreate(
                ['slug' => 'tenant-'.$slug],
                [
                    'name' => $definition['name'],
                    'scope' => 'tenant',
                    'description' => 'Default permissions for the '.$definition['name'].' role in a business.',
                    'is_system' => true,
                ],
            );

            // Mirrors RoleProvisioningService's own sentinel handling —
            // '*' is every tenant permission, '*.view' every read one.
            $permissionIds = match (true) {
                $definition['permissions'] === ['*'] => $idsBySlug->values(),
                $definition['permissions'] === ['*.view'] => $viewIds->values(),
                default => $idsBySlug->only($definition['permissions'])->values(),
            };

            $template->permissions()->sync($permissionIds);
        }
    }
}
