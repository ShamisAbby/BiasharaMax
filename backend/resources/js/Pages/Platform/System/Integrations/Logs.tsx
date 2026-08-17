import BiBadge from '@/Components/Bi/BiBadge';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

interface LogRow {
    id: string;
    direction: 'inbound' | 'outbound';
    event_type: string;
    status_code: number | null;
    is_successful: boolean;
    error_message: string | null;
    response_payload: Record<string, unknown> | null;
    created_at: string;
}

export default function IntegrationLogs({
    integration,
    logs,
}: {
    integration: { id: string; name: string };
    logs: {
        data: LogRow[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
}) {
    const [expanded, setExpanded] = useState<string | null>(null);

    const columns: BiTableColumn<LogRow>[] = [
        { key: 'event', label: 'Event', render: (l) => l.event_type },
        { key: 'direction', label: 'Direction', render: (l) => l.direction },
        {
            key: 'result',
            label: 'Result',
            render: (l) => (
                <BiBadge variant={l.is_successful ? 'success' : 'danger'}>
                    {l.is_successful ? 'Success' : 'Failed'}{' '}
                    {l.status_code ? `(${l.status_code})` : ''}
                </BiBadge>
            ),
        },
        { key: 'error', label: 'Error', render: (l) => l.error_message ?? '—' },
        {
            key: 'date',
            label: 'Date',
            render: (l) => new Date(l.created_at).toLocaleString(),
        },
        {
            key: 'actions',
            label: '',
            align: 'right',
            render: (l) => (
                <button
                    onClick={() => setExpanded(expanded === l.id ? null : l.id)}
                    className="text-indigo-600 hover:underline"
                >
                    {expanded === l.id ? 'Hide' : 'View'} response
                </button>
            ),
        },
    ];

    const list = Array.isArray(logs) ? logs : logs.data;
    const expandedLog = list.find((l) => l.id === expanded);

    return (
        <PlatformLayout>
            <div className="space-y-4">
                <Link
                    href={route('platform.system.integrations.index')}
                    className="text-sm text-indigo-600 hover:underline"
                >
                    ← Back to integrations
                </Link>

                <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    {integration.name} — Logs
                </h1>

                <BiDataGrid
                    columns={columns}
                    paginated={logs}
                    rowKey={(l) => l.id}
                    emptyMessage="No logs recorded yet for this integration."
                />

                {expandedLog && (
                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Response
                        </h3>
                        <pre className="overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100">
                            {JSON.stringify(
                                expandedLog.response_payload,
                                null,
                                2,
                            )}
                        </pre>
                    </div>
                )}
            </div>
        </PlatformLayout>
    );
}
