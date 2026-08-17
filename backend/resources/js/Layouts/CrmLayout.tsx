import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

const TABS = [
    { name: 'crm.dashboard', label: 'Dashboard' },
    { name: 'sales.customers.index', label: 'Customers' },
    { name: 'crm.customer-groups.index', label: 'Groups' },
    { name: 'crm.customer-tags.index', label: 'Tags' },
    { name: 'crm.loyalty.dashboard', label: 'Loyalty' },
    { name: 'crm.loyalty-tiers.index', label: 'Tiers' },
    { name: 'crm.loyalty-rewards.index', label: 'Rewards' },
    { name: 'crm.feedback.index', label: 'Feedback' },
    { name: 'crm.campaigns.index', label: 'Campaigns' },
];

export default function CrmLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    CRM
                </h2>
            }
        >
            <Head title={`CRM — ${title}`} />

            <div className="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div className="mx-auto overflow-x-auto px-4 sm:px-6 lg:px-8">
                    <nav className="-mb-px flex gap-6">
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
            </div>

            <div className="py-8">
                {/* No max-width cap: these are data-dense table screens, and
                    /dashboard already runs full-bleed — capping here made the
                    content width jump every time you moved between modules. */}
                <div className="mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
                    {children}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
