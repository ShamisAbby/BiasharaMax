import Dropdown from '@/Components/Dropdown';
import {
    ArrowUpTrayIcon,
    BellIcon,
    ClockIcon,
    EnvelopeIcon,
    ExclamationTriangleIcon,
    TruckIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

type NotificationItem = {
    id: string;
    title: string;
    message: string;
    url: string | null;
    icon: string | null;
    read_at: string | null;
    created_at: string;
};

const ICONS: Record<string, typeof BellIcon> = {
    'exclamation-triangle': ExclamationTriangleIcon,
    clock: ClockIcon,
    'x-circle': XCircleIcon,
    truck: TruckIcon,
    'arrow-up-tray': ArrowUpTrayIcon,
    envelope: EnvelopeIcon,
};

const POLL_INTERVAL_MS = 30000;

export default function NotificationBell() {
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loaded, setLoaded] = useState(false);

    const fetchNotifications = useCallback(async () => {
        const response = await window.axios.get(route('notifications.index'));
        setNotifications(response.data.notifications);
        setUnreadCount(response.data.unread_count);
        setLoaded(true);
    }, []);

    useEffect(() => {
        fetchNotifications();
        const interval = setInterval(fetchNotifications, POLL_INTERVAL_MS);
        return () => clearInterval(interval);
    }, [fetchNotifications]);

    const markAsRead = useCallback(async (notification: NotificationItem) => {
        if (notification.read_at) {
            return;
        }
        await window.axios.post(route('notifications.read', notification.id));
        setNotifications((previous) =>
            previous.map((item) =>
                item.id === notification.id
                    ? { ...item, read_at: new Date().toISOString() }
                    : item,
            ),
        );
        setUnreadCount((previous) => Math.max(0, previous - 1));
    }, []);

    const markAllAsRead = useCallback(async () => {
        await window.axios.post(route('notifications.read-all'));
        setNotifications((previous) =>
            previous.map((item) => ({
                ...item,
                read_at: item.read_at ?? new Date().toISOString(),
            })),
        );
        setUnreadCount(0);
    }, []);

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="relative inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                >
                    <BellIcon className="h-6 w-6" />
                    {unreadCount > 0 && (
                        <span className="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </button>
            </Dropdown.Trigger>

            <Dropdown.Content width="80" align="right">
                <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-700">
                    <span className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Notifications
                    </span>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={markAllAsRead}
                            className="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                        >
                            Mark all as read
                        </button>
                    )}
                </div>

                <div className="max-h-96 overflow-y-auto">
                    {loaded && notifications.length === 0 && (
                        <p className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            You're all caught up.
                        </p>
                    )}

                    {notifications.map((notification) => {
                        const Icon = ICONS[notification.icon ?? ''] ?? BellIcon;
                        const isUnread = !notification.read_at;

                        const content = (
                            <div
                                className={`flex gap-3 px-4 py-3 text-sm ${
                                    isUnread
                                        ? 'bg-indigo-50 dark:bg-indigo-900/20'
                                        : ''
                                }`}
                            >
                                <Icon className="mt-0.5 h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                                <div className="min-w-0">
                                    <p className="font-medium text-gray-800 dark:text-gray-200">
                                        {notification.title}
                                    </p>
                                    <p className="truncate text-gray-500 dark:text-gray-400">
                                        {notification.message}
                                    </p>
                                </div>
                            </div>
                        );

                        return notification.url ? (
                            <Link
                                key={notification.id}
                                href={notification.url}
                                onClick={() => markAsRead(notification)}
                                className="block border-b border-gray-50 last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-700"
                            >
                                {content}
                            </Link>
                        ) : (
                            <button
                                key={notification.id}
                                type="button"
                                onClick={() => markAsRead(notification)}
                                className="block w-full border-b border-gray-50 text-left last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-700"
                            >
                                {content}
                            </button>
                        );
                    })}
                </div>
            </Dropdown.Content>
        </Dropdown>
    );
}
