import BiBadge from '@/Components/Bi/BiBadge';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformRbacLayout from '@/Layouts/PlatformRbacLayout';
import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface PermissionRow {
    id: string;
    module: string;
    scope: 'tenant' | 'platform';
    action: string;
    name: string;
    slug: string;
}

interface PlatformRoleColumn {
    id: string;
    name: string;
    permission_ids: string[];
}

export default function PermissionMatrixIndex({
    permissions: list,
    platformRoles,
    modules,
    filters,
}: {
    permissions: PermissionRow[];
    platformRoles: PlatformRoleColumn[];
    modules: string[];
    filters: Record<string, string>;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('platform.rbac.permission-matrix.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const onSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    return (
        <PlatformRbacLayout title="Permission Matrix">
            <div className="space-y-4">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Browse every permission in the system (tenant + platform
                    scope) and see which platform roles grant it. Per-business
                    tenant role assignment is managed on each business's Roles
                    settings page.
                </p>

                <div className="flex flex-wrap gap-3">
                    <form onSubmit={onSearchSubmit} className="flex gap-2">
                        <TextInput
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or slug"
                            className="w-64"
                        />
                    </form>

                    <SelectInput
                        value={filters.scope ?? ''}
                        onChange={(e) =>
                            applyFilters({ scope: e.target.value })
                        }
                    >
                        <option value="">All scopes</option>
                        <option value="tenant">Tenant</option>
                        <option value="platform">Platform</option>
                    </SelectInput>

                    <SelectInput
                        value={filters.module ?? ''}
                        onChange={(e) =>
                            applyFilters({ module: e.target.value })
                        }
                    >
                        <option value="">All modules</option>
                        {modules.map((module) => (
                            <option key={module} value={module}>
                                {module}
                            </option>
                        ))}
                    </SelectInput>
                </div>

                <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                    Permission
                                </th>
                                <th className="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                    Module
                                </th>
                                <th className="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                    Scope
                                </th>
                                {platformRoles.map((role) => (
                                    <th
                                        key={role.id}
                                        className="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400"
                                    >
                                        {role.name}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            {list.map((permission) => (
                                <tr key={permission.id}>
                                    <td className="px-4 py-2">
                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {permission.name}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            {permission.slug}
                                        </p>
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                        {permission.module}
                                    </td>
                                    <td className="px-4 py-2">
                                        <BiBadge
                                            variant={
                                                permission.scope === 'platform'
                                                    ? 'warning'
                                                    : 'info'
                                            }
                                        >
                                            {permission.scope}
                                        </BiBadge>
                                    </td>
                                    {platformRoles.map((role) => (
                                        <td
                                            key={role.id}
                                            className="px-4 py-2 text-center"
                                        >
                                            {role.permission_ids.includes(
                                                permission.id,
                                            ) ? (
                                                <span className="text-emerald-600">
                                                    ✓
                                                </span>
                                            ) : (
                                                <span className="text-gray-300 dark:text-gray-600">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                            {list.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={3 + platformRoles.length}
                                        className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        No permissions match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </PlatformRbacLayout>
    );
}
