import BiBadge from '@/Components/Bi/BiBadge';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import PlatformFinanceLayout from '@/Layouts/PlatformFinanceLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

interface LogRow {
    id: string;
    direction: 'inbound' | 'outbound';
    event_type: string;
    status_code: number | null;
    is_successful: boolean;
    error_message: string | null;
    request_payload: Record<string, unknown> | null;
    response_payload: Record<string, unknown> | null;
    created_at: string;
}

export default function GatewayLogs({
    gateway,
    logs,
}: {
    gateway: { id: string; name: string };
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
                    {expanded === l.id ? 'Hide' : 'View'} payload
                </button>
            ),
        },
    ];

    const list = Array.isArray(logs) ? logs : logs.data;
    const expandedLog = list.find((l) => l.id === expanded);

    return (
        <PlatformFinanceLayout title={`${gateway.name} Logs`}>
            <div className="space-y-4">
                <Link
                    href={route('platform.finance.gateways.index')}
                    className="text-sm text-indigo-600 hover:underline"
                >
                    ← Back to gateways
                </Link>

                <BiDataGrid
                    columns={columns}
                    paginated={logs}
                    rowKey={(l) => l.id}
                    emptyMessage="No logs recorded yet for this gateway."
                />

                {expandedLog && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <h3 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Request
                            </h3>
                            <pre className="overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100">
                                {JSON.stringify(
                                    expandedLog.request_payload,
                                    null,
                                    2,
                                )}
                            </pre>
                        </div>
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
                    </div>
                )}
            </div>
        </PlatformFinanceLayout>
    );
}
