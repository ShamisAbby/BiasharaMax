import BiBadge from '@/Components/Bi/BiBadge';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface AuditLogRow {
    id: string;
    module: string | null;
    action: string;
    actor_type: string | null;
    actor_id: string | null;
    auditable_type: string | null;
    auditable_id: string | null;
    business: { id: string; name: string } | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    browser: string | null;
    operating_system: string | null;
    device_type: string | null;
    country: string | null;
    risk_level: string;
    created_at: string;
}

interface PaginatedLogs {
    data: AuditLogRow[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const ACTION_VARIANT: Record<string, 'success' | 'info' | 'danger'> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
};

const RISK_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral'
> = {
    low: 'neutral',
    normal: 'success',
    elevated: 'warning',
    high: 'danger',
};

export default function PlatformAuditLogsIndex({
    logs,
    filters,
}: {
    logs: PaginatedLogs;
    filters: Record<string, string>;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('platform.audit-logs.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const onSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const columns: BiTableColumn<AuditLogRow>[] = [
        {
            key: 'action',
            label: 'Action',
            render: (log) => (
                <BiBadge variant={ACTION_VARIANT[log.action] ?? 'info'}>
                    {log.action}
                </BiBadge>
            ),
        },
        {
            key: 'subject',
            label: 'Subject',
            render: (log) => (
                <>
                    <p className="font-medium text-gray-900 dark:text-gray-100">
                        {log.auditable_type ?? '—'}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {log.business?.name ?? 'Platform'}
                    </p>
                </>
            ),
        },
        {
            key: 'actor',
            label: 'Actor',
            render: (log) => (
                <span className="text-sm text-gray-700 dark:text-gray-300">
                    {log.actor_type ?? 'system'}
                </span>
            ),
        },
        {
            key: 'device',
            label: 'Device',
            render: (log) => (
                <span className="text-sm text-gray-700 dark:text-gray-300">
                    {log.browser ?? '—'} / {log.operating_system ?? '—'} (
                    {log.device_type ?? 'unknown'})
                </span>
            ),
        },
        {
            key: 'ip',
            label: 'IP address',
            render: (log) => log.ip_address ?? '—',
        },
        {
            key: 'risk',
            label: 'Risk',
            render: (log) => (
                <BiBadge variant={RISK_VARIANT[log.risk_level] ?? 'neutral'}>
                    {log.risk_level}
                </BiBadge>
            ),
        },
        {
            key: 'when',
            label: 'When',
            align: 'right',
            render: (log) => new Date(log.created_at).toLocaleString(),
        },
    ];

    return (
        <PlatformLayout>
            <Head title="Audit Logs" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Audit Logs
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Every create, update and delete across the platform
                            — {logs.meta.total} entries.
                        </p>
                    </div>
                    <a href={route('platform.audit-logs.export', filters)}>
                        <SecondaryButton type="button">
                            Export CSV
                        </SecondaryButton>
                    </a>
                </div>

                <BiDataGrid
                    columns={columns}
                    paginated={logs}
                    rowKey={(log) => log.id}
                    emptyMessage="No audit log entries match these filters."
                    toolbar={
                        <>
                            <form
                                onSubmit={onSearchSubmit}
                                className="flex gap-2"
                            >
                                <TextInput
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by model or business"
                                    className="w-72"
                                />
                                <SecondaryButton type="submit">
                                    Search
                                </SecondaryButton>
                            </form>

                            <SelectInput
                                value={filters.action ?? ''}
                                onChange={(e) =>
                                    applyFilters({ action: e.target.value })
                                }
                            >
                                <option value="">All actions</option>
                                <option value="created">Created</option>
                                <option value="updated">Updated</option>
                                <option value="deleted">Deleted</option>
                            </SelectInput>

                            <SelectInput
                                value={filters.actor_type ?? ''}
                                onChange={(e) =>
                                    applyFilters({ actor_type: e.target.value })
                                }
                            >
                                <option value="">All actors</option>
                                <option value="user">Business user</option>
                                <option value="platform_user">
                                    SuperAdmin
                                </option>
                            </SelectInput>

                            <SelectInput
                                value={filters.risk_level ?? ''}
                                onChange={(e) =>
                                    applyFilters({ risk_level: e.target.value })
                                }
                            >
                                <option value="">All risk levels</option>
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="elevated">Elevated</option>
                                <option value="high">High</option>
                            </SelectInput>
                        </>
                    }
                />
            </div>
        </PlatformLayout>
    );
}
