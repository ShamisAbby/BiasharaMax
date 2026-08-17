import BiBadge from '@/Components/Bi/BiBadge';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Link, router } from '@inertiajs/react';

interface DeliveryRow {
    id: string;
    event: string;
    response_status: number | null;
    is_successful: boolean;
    attempt: number;
    delivered_at: string | null;
    created_at: string;
}

export default function WebhookDeliveries({
    webhook,
    deliveries,
}: {
    webhook: { id: string; name: string };
    deliveries: {
        data: DeliveryRow[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
}) {
    const { notify } = useBiNotification();

    const retry = (delivery: DeliveryRow) => {
        router.post(
            route(
                'platform.operations.developer.webhooks.deliveries.retry',
                delivery.id,
            ),
            {},
            {
                onSuccess: () => notify('Delivery retried.', 'success'),
            },
        );
    };

    const columns: BiTableColumn<DeliveryRow>[] = [
        { key: 'event', label: 'Event', render: (d) => d.event },
        {
            key: 'status',
            label: 'Status',
            render: (d) => (
                <BiBadge variant={d.is_successful ? 'success' : 'danger'}>
                    {d.response_status ?? 'Error'}
                </BiBadge>
            ),
        },
        { key: 'attempt', label: 'Attempt', render: (d) => d.attempt },
        {
            key: 'when',
            label: 'When',
            render: (d) => new Date(d.created_at).toLocaleString(),
        },
        {
            key: 'actions',
            label: '',
            align: 'right',
            render: (d) =>
                !d.is_successful && (
                    <button
                        onClick={() => retry(d)}
                        className="text-indigo-600 hover:underline"
                    >
                        Retry
                    </button>
                ),
        },
    ];

    return (
        <PlatformLayout>
            <div className="space-y-4">
                <Link
                    href={route('platform.operations.developer.index')}
                    className="text-sm text-indigo-600 hover:underline"
                >
                    ← Back to Developer Center
                </Link>
                <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Webhook Logs — {webhook.name}
                </h1>

                <BiDataGrid
                    columns={columns}
                    paginated={deliveries}
                    rowKey={(d) => d.id}
                    emptyMessage="No deliveries recorded yet."
                />
            </div>
        </PlatformLayout>
    );
}
