import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

const TABS = [
    {
        name: 'payroll.dashboard',
        label: 'HR Dashboard',
        active: (current: string) => current === 'payroll.dashboard',
    },
    {
        name: 'payroll.employees.index',
        label: 'Employees',
        active: (current: string) =>
            current === 'payroll.employees.index' ||
            current === 'payroll.employees.show',
    },
    {
        name: 'payroll.periods.index',
        label: 'Payroll Periods',
        active: (current: string) =>
            current === 'payroll.periods.index' ||
            current === 'payroll.periods.show',
    },
    {
        name: 'payroll.attendance.index',
        label: 'Attendance',
        active: (current: string) =>
            (current ?? '').startsWith('payroll.attendance'),
    },
    {
        name: 'payroll.leave.index',
        label: 'Leave',
        active: (current: string) => current === 'payroll.leave.index',
    },
    {
        name: 'payroll.leave-types.index',
        label: 'Leave Types',
        active: (current: string) => current === 'payroll.leave-types.index',
    },
];

export default function PayrollLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    const current = route().current() ?? '';

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Human Resources
                </h2>
            }
        >
            <Head title={`HR — ${title}`} />

            <div className="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div className="mx-auto overflow-x-auto px-4 sm:px-6 lg:px-8">
                    <nav className="-mb-px flex gap-6">
                        {TABS.map((tab) => {
                            const isActive = tab.active(current);

                            return (
                                <Link
                                    key={tab.name}
                                    href={route(tab.name)}
                                    className={`whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors ${
                                        isActive
                                            ? 'border-indigo-600 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
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
