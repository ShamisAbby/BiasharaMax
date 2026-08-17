<?php

namespace App\Domain\RBAC\Services;

use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\PlatformRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Seeds the default platform staff roles.
 *
 * Roles are organised by job function rather than by seniority, so a
 * person is granted the set that matches what they actually do. Every
 * grant below is an explicit list of permission slugs — with two
 * sentinels for the cases where an explicit list would silently rot as
 * new permissions are added:
 *
 *   '*'       every platform-scope permission
 *   '*.view'  every platform-scope read permission
 *
 * Because the sentinels resolve at run time, re-running the seeder after
 * a sprint that adds new permissions keeps Super Admin and the read-only
 * roles complete automatically. Role-specific lists are deliberately NOT
 * auto-expanded: a new capability should be granted to a narrow role
 * only by an explicit decision here.
 *
 * Everything is `updateOrCreate` + `sync`, so this is safe to re-run.
 * Note that `sync` means any hand-tuning of a SYSTEM role in the UI is
 * reverted on the next run — system roles are owned by this file.
 * Roles created by an admin (is_system = false) are never touched.
 */
class PlatformRoleProvisioningService
{
    /** Every platform-scope permission. */
    private const ALL = '*';

    /** Every platform-scope permission ending in `.view`. */
    private const ALL_READ = '*.view';

    /**
     * The privilege-escalation boundary: these let a role rewrite the
     * RBAC system itself, so anyone holding them can grant themselves
     * anything else. Only Super Admin gets them.
     *
     * @var list<string>
     */
    private const RBAC_CONFIGURATION_SLUGS = [
        'platform_roles.create',
        'platform_roles.update',
        'platform_roles.delete',
        'platform_roles.manage',
        'role_templates.manage',
    ];

    /**
     * slug => [name, description, permission slugs].
     *
     * @var array<string, array{name: string, description: string, permissions: list<string>}>
     */
    private const ROLES = [
        PlatformRole::SUPER_ADMIN => [
            'name' => 'Super Admin',
            'description' => 'Unrestricted access to every platform capability, including platform RBAC itself.',
            'permissions' => [self::ALL],
        ],

        PlatformRole::PLATFORM_ADMIN => [
            'name' => 'Platform Admin',
            'description' => 'Day-to-day platform operations, excluding platform RBAC configuration.',
            // Resolved as "everything except the RBAC configuration
            // boundary" — see permissionIdsFor().
            'permissions' => [self::ALL],
        ],

        'operations-manager' => [
            'name' => 'Operations Manager',
            'description' => 'Runs the tenant estate: businesses, subscriptions, licences and the catalogue of business types and modules.',
            'permissions' => [
                'businesses.manage',
                'subscriptions.manage',
                'licenses.manage',
                'business_types.view', 'business_types.create', 'business_types.update',
                'business_types.delete', 'business_types.archive', 'business_types.manage',
                'modules.view', 'modules.create', 'modules.update', 'modules.delete', 'modules.manage',
                'website_templates.view',
                'monitoring.view',
                'support.view',
                'audit_logs.view',
                'ai_insights.view',
            ],
        ],

        'finance-manager' => [
            'name' => 'Finance Manager',
            'description' => 'Full control of money movement: payments, refunds, gateways, subscriptions and finance reporting.',
            'permissions' => [
                'payments.view', 'payments.manage', 'payments.refund',
                'payment_gateways.view', 'payment_gateways.manage',
                'finance_reports.view', 'finance_reports.export',
                'subscriptions.manage',
                'licenses.manage',
                'audit_logs.view',
            ],
        ],

        'billing-specialist' => [
            'name' => 'Billing Specialist',
            'description' => 'Processes day-to-day billing. Deliberately excludes refunds and gateway configuration — both are Finance Manager decisions.',
            'permissions' => [
                'payments.view', 'payments.manage',
                'payment_gateways.view',
                'finance_reports.view',
                'subscriptions.manage',
            ],
        ],

        'support-manager' => [
            'name' => 'Support Manager',
            'description' => 'Owns the support function: tickets, departments, agents, the knowledge base and customer-facing announcements.',
            'permissions' => [
                'support.view', 'support.manage',
                'platform_notifications.view', 'platform_notifications.manage', 'platform_notifications.send',
                'businesses.manage',
                'audit_logs.view',
            ],
        ],

        'support-agent' => [
            'name' => 'Support Agent',
            'description' => 'Handles tickets. Read-only everywhere else, with no ability to reconfigure the support setup itself.',
            'permissions' => [
                'support.view',
                'platform_notifications.view',
            ],
        ],

        'security-officer' => [
            'name' => 'Security Officer',
            'description' => 'Blocks IPs, unlocks accounts and investigates incidents through the audit trail.',
            'permissions' => [
                'security.view', 'security.manage',
                'audit_logs.view', 'audit_logs.export',
                'monitoring.view',
                'platform_users.manage',
            ],
        ],

        'compliance-auditor' => [
            'name' => 'Compliance Auditor',
            'description' => 'Read-only across the areas an audit touches. Can export evidence but change nothing.',
            'permissions' => [
                'audit_logs.view', 'audit_logs.export',
                'finance_reports.view', 'finance_reports.export',
                'payments.view',
                'security.view',
                'monitoring.view',
                'platform_settings.view',
                'platform_roles.view',
                'role_templates.view',
            ],
        ],

        'platform-engineer' => [
            'name' => 'Platform Engineer',
            'description' => 'Technical operations: webhooks, integrations, monitoring and backups.',
            'permissions' => [
                'developer.view', 'developer.manage',
                'integrations.view', 'integrations.manage',
                'monitoring.view',
                'backups.manage',
                'platform_settings.view',
                'audit_logs.view',
            ],
        ],

        'content-manager' => [
            'name' => 'Content Manager',
            'description' => 'Maintains website templates and everything the platform sends out to tenants.',
            'permissions' => [
                'website_templates.view', 'website_templates.manage',
                'platform_notifications.view', 'platform_notifications.manage', 'platform_notifications.send',
                'business_types.view',
                'support.view',
            ],
        ],

        'data-analyst' => [
            'name' => 'Data Analyst',
            'description' => 'Reads everything, changes nothing. Automatically covers every read permission, including ones added later.',
            'permissions' => [self::ALL_READ],
        ],
    ];

    /**
     * The role catalogue, exposed so RoleTemplateSeeder can build a
     * starter template per role without restating the permission lists.
     *
     * @return array<string, array{name: string, description: string, permissions: list<string>}>
     */
    public static function catalogue(): array
    {
        return self::ROLES;
    }

    /**
     * Resolves a catalogue entry's grants to permission IDs. Public for
     * the same reason as catalogue().
     *
     * @param  list<string>  $grants
     * @param  Collection<string, string>  $permissionIdsBySlug
     * @return list<string>
     */
    public function resolvePermissionIds(string $roleSlug, array $grants, Collection $permissionIdsBySlug): array
    {
        return $this->permissionIdsFor($roleSlug, $grants, $permissionIdsBySlug);
    }

    public function provisionDefaultRoles(): void
    {
        /** @var Collection<string, string> $permissionIdsBySlug */
        $permissionIdsBySlug = Permission::query()
            ->where('scope', Permission::SCOPE_PLATFORM)
            ->pluck('id', 'slug');

        foreach (self::ROLES as $slug => $definition) {
            $role = PlatformRole::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );

            $role->permissions()->sync(
                $this->permissionIdsFor($slug, $definition['permissions'], $permissionIdsBySlug),
            );
        }
    }

    /**
     * @param  list<string>  $grants
     * @param  Collection<string, string>  $permissionIdsBySlug
     * @return list<string>
     */
    private function permissionIdsFor(string $roleSlug, array $grants, Collection $permissionIdsBySlug): array
    {
        $resolved = $permissionIdsBySlug;

        if (! in_array(self::ALL, $grants, true)) {
            if (in_array(self::ALL_READ, $grants, true)) {
                $resolved = $permissionIdsBySlug->filter(
                    fn (string $id, string $slug): bool => Str::endsWith($slug, '.view'),
                );
            } else {
                // `only()` drops unknown keys silently, so a typo in the
                // table above would quietly hand out fewer permissions
                // than intended and nothing would ever say so. Fail at
                // seed time instead.
                $unknown = array_diff($grants, $permissionIdsBySlug->keys()->all());

                if ($unknown !== []) {
                    throw new InvalidArgumentException(sprintf(
                        'Platform role [%s] grants unknown permission(s): %s. Add them to PermissionSeeder::PLATFORM_PERMISSIONS or correct the slug.',
                        $roleSlug,
                        implode(', ', $unknown),
                    ));
                }

                $resolved = $permissionIdsBySlug->only($grants);
            }
        }

        // Platform Admin is "everything except the escalation boundary".
        // Expressed here rather than as a hand-maintained list so it can
        // never drift out of date as permissions are added.
        if ($roleSlug === PlatformRole::PLATFORM_ADMIN) {
            $resolved = $resolved->except(self::RBAC_CONFIGURATION_SLUGS);
        }

        return $resolved->values()->all();
    }
}
