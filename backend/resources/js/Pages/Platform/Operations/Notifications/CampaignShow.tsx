import BiBadge from '@/Components/Bi/BiBadge';
import BiCard from '@/Components/Bi/BiCard';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Link, router } from '@inertiajs/react';

interface DeliveryRow {
    id: string;
    notifiable_type: string;
    recipient: string;
    status: string;
    error_message: string | null;
    sent_at: string | null;
}

interface CampaignDetail {
    id: string;
    name: string;
    channel: string;
    status: string;
    total_recipients: number;
    sent_count: number;
    failed_count: number;
    deliveries: DeliveryRow[];
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral'
> = {
    pending: 'warning',
    sent: 'success',
    delivered: 'success',
    failed: 'danger',
    read: 'neutral',
};

export default function CampaignShow({
    campaign,
}: {
    campaign: CampaignDetail;
}) {
    const { notify } = useBiNotification();

    const retry = (delivery: DeliveryRow) => {
        router.post(
            route(
                'platform.operations.notifications.deliveries.retry',
                delivery.id,
            ),
            {},
            {
                onSuccess: () => notify('Delivery retried.', 'success'),
            },
        );
    };

    return (
        <PlatformLayout>
            <div className="space-y-6">
                <Link
                    href={route('platform.operations.notifications.index')}
                    className="text-sm text-indigo-600 hover:underline"
                >
                    ← Back to notifications
                </Link>

                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {campaign.name}
                    </h1>
                    <BiBadge variant="info">{campaign.status}</BiBadge>
                </div>

                <BiCard title="Delivery Report">
                    <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        {campaign.sent_count} sent · {campaign.failed_count}{' '}
                        failed · {campaign.total_recipients} total recipients
                    </p>

                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {campaign.deliveries.map((delivery) => (
                            <div
                                key={delivery.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <div>
                                    <p className="text-gray-900 dark:text-gray-100">
                                        {delivery.recipient}
                                    </p>
                                    {delivery.error_message && (
                                        <p className="text-xs text-red-600">
                                            {delivery.error_message}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-3">
                                    <BiBadge
                                        variant={
                                            STATUS_VARIANT[delivery.status] ??
                                            'neutral'
                                        }
                                    >
                                        {delivery.status}
                                    </BiBadge>
                                    {delivery.status === 'failed' && (
                                        <button
                                            onClick={() => retry(delivery)}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            Retry
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                        {campaign.deliveries.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No deliveries recorded yet.
                            </p>
                        )}
                    </div>
                </BiCard>
            </div>
        </PlatformLayout>
    );
}
