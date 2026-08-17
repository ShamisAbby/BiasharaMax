import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

const TABS = [
    { name: 'platform.subscriptions.dashboard', label: 'Dashboard' },
    { name: 'platform.subscriptions.plans.index', label: 'Plans' },
    { name: 'platform.subscriptions.subscribers.index', label: 'Subscribers' },
    {
        name: 'platform.subscriptions.transactions.index',
        label: 'Transactions',
    },
];

export default function PlatformSubscriptionsLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    return (
        <PlatformLayout>
            <Head title={`Subscriptions — ${title}`} />

            <div className="mb-6 border-b border-gray-200 dark:border-gray-700">
                <nav className="-mb-px flex gap-6 overflow-x-auto">
                    {TABS.map((tab) => {
                        const active = route().current(tab.name);

                        return (
                            <Link
                                key={tab.name}
                                href={route(tab.name)}
                                className={`whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium ${
                                    active
                                        ? 'border-indigo-600 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'
                                }`}
                            >
                                {tab.label}
                            </Link>
                        );
                    })}
                </nav>
            </div>

            {children}
        </PlatformLayout>
    );
}
