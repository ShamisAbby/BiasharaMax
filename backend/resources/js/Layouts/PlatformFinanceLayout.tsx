import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

const TABS = [
    { name: 'platform.finance.dashboard', label: 'Dashboard' },
    { name: 'platform.finance.payments.index', label: 'Payments' },
    { name: 'platform.finance.gateways.index', label: 'Payment Gateways' },
    { name: 'platform.finance.reports.index', label: 'Reports' },
];

export default function PlatformFinanceLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    return (
        <PlatformLayout>
            <Head title={`Finance — ${title}`} />

            <div className="mb-6 border-b border-gray-200 dark:border-gray-700">
                <nav className="-mb-px flex gap-6 overflow-x-auto">
                    {TABS.map((tab) => {
                        const active =
                            route().current(tab.name) ||
                            route().current(tab.name + '.*');

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
