import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    ArrowUpTrayIcon,
    BellIcon,
    CheckIcon,
    ClockIcon,
    EnvelopeIcon,
    ExclamationTriangleIcon,
    TruckIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { Head, Link, router } from '@inertiajs/react';

interface NotificationItem {
    id: string;
    title: string;
    message: string;
    url: string | null;
    icon: string | null;
    read_at: string | null;
    created_at: string;
}

const ICONS: Record<string, React.ElementType> = {
    'exclamation-triangle': ExclamationTriangleIcon,
    clock: ClockIcon,
    'x-circle': XCircleIcon,
    truck: TruckIcon,
    'arrow-up-tray': ArrowUpTrayIcon,
    envelope: EnvelopeIcon,
};

function timeAgo(dateStr: string): string {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

export default function NotificationsIndex({
    notifications,
    unread_count,
}: {
    notifications: NotificationItem[];
    unread_count: number;
}) {
    function markAllRead() {
        window.axios.post(route('notifications.read-all')).then(() => {
            router.reload({ only: ['notifications', 'unread_count'] });
        });
    }

    function markRead(n: NotificationItem) {
        if (n.read_at) return;
        window.axios.post(route('notifications.read', n.id)).then(() => {
            router.reload({ only: ['notifications', 'unread_count'] });
        });
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Notifications
                </h2>
            }
        >
            <Head title="Notifications" />
            <div className="py-8">
                <div className="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-500">
                            {unread_count > 0 ? (
                                <span className="font-semibold text-indigo-600">
                                    {unread_count} unread
                                </span>
                            ) : (
                                'All caught up'
                            )}
                        </p>
                        {unread_count > 0 && (
                            <button
                                onClick={markAllRead}
                                className="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                <CheckIcon className="h-4 w-4" />
                                Mark all as read
                            </button>
                        )}
                    </div>

                    {notifications.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl bg-white py-16 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                            <BellIcon className="h-12 w-12 text-gray-300 dark:text-gray-600" />
                            <p className="mt-4 font-medium text-gray-500 dark:text-gray-400">
                                No notifications yet
                            </p>
                            <p className="mt-1 text-sm text-gray-400">
                                You'll see alerts and updates here.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                            {notifications.map((n, i) => {
                                const Icon = ICONS[n.icon ?? ''] ?? BellIcon;
                                const isUnread = !n.read_at;
                                const content = (
                                    <div
                                        className={`flex items-start gap-4 px-5 py-4 ${
                                            isUnread
                                                ? 'bg-indigo-50 dark:bg-indigo-900/10'
                                                : ''
                                        } ${i !== 0 ? 'border-t border-gray-100 dark:border-gray-700' : ''}`}
                                    >
                                        <div
                                            className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${isUnread ? 'bg-indigo-100 dark:bg-indigo-900/30' : 'bg-gray-100 dark:bg-gray-700'}`}
                                        >
                                            <Icon
                                                className={`h-5 w-5 ${isUnread ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400'}`}
                                            />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-2">
                                                <p
                                                    className={`text-sm font-medium ${isUnread ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'}`}
                                                >
                                                    {n.title}
                                                </p>
                                                <span className="shrink-0 text-xs text-gray-400">
                                                    {timeAgo(n.created_at)}
                                                </span>
                                            </div>
                                            <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                                {n.message}
                                            </p>
                                        </div>
                                        {isUnread && (
                                            <span className="mt-2 h-2 w-2 shrink-0 rounded-full bg-indigo-500" />
                                        )}
                                    </div>
                                );

                                return n.url ? (
                                    <Link
                                        key={n.id}
                                        href={n.url}
                                        onClick={() => markRead(n)}
                                        className="block transition hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                    >
                                        {content}
                                    </Link>
                                ) : (
                                    <button
                                        key={n.id}
                                        type="button"
                                        onClick={() => markRead(n)}
                                        className="block w-full text-left transition hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                    >
                                        {content}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
