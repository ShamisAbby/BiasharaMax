import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

const TABS = [
    { name: 'finance.dashboard', label: 'Dashboard' },
    { name: 'finance.journal.index', label: 'Journal Entries' },
    { name: 'finance.accounts.index', label: 'Chart of Accounts' },
    { name: 'finance.ledger.index', label: 'Ledger' },
    { name: 'finance.bank.index', label: 'Bank' },
    { name: 'finance.periods.index', label: 'Periods' },
    { name: 'finance.budgets.index', label: 'Budgets' },
    { name: 'finance.tax.configure', label: 'Tax' },
    { name: 'finance.assets.index', label: 'Assets' },
    { name: 'finance.reports.trial-balance', label: 'Reports' },
    { name: 'finance.settings.currencies', label: 'Settings' },
];

const REPORTS_ROUTES = [
    'finance.reports.trial-balance',
    'finance.reports.profit-and-loss',
    'finance.reports.balance-sheet',
    'finance.reports.cash-flow',
];

export default function FinanceLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Finance
                </h2>
            }
        >
            <Head title={`Finance — ${title}`} />

            <div className="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div className="mx-auto overflow-x-auto px-4 sm:px-6 lg:px-8">
                    <nav className="-mb-px flex gap-6">
                        {TABS.map((tab) => {
                            const active =
                                tab.name === 'finance.reports.trial-balance'
                                    ? REPORTS_ROUTES.some((r) =>
                                          route().current(r),
                                      )
                                    : route().current(tab.name) ||
                                      (tab.name === 'finance.ledger.index' &&
                                          route().current(
                                              'finance.ledger.show',
                                          )) ||
                                      (tab.name === 'finance.bank.index' &&
                                          (route().current(
                                              'finance.bank.show',
                                          ) ||
                                              route().current(
                                                  'finance.bank.reconciliations.index',
                                              ))) ||
                                      (tab.name === 'finance.budgets.index' &&
                                          route().current(
                                              'finance.budgets.show',
                                          )) ||
                                      (tab.name === 'finance.tax.configure' &&
                                          (route().current(
                                              'finance.tax.vat-return',
                                          ) ||
                                              route().current(
                                                  'finance.tax.income-tax',
                                              ))) ||
                                      (tab.name === 'finance.assets.index' &&
                                          route().current(
                                              'finance.assets.show',
                                          ));

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
