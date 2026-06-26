import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Business, Subscription } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({
    business,
    subscription,
    trialDaysRemaining,
    employeeCount,
}: {
    business: Business | null;
    subscription: Subscription | null;
    trialDaysRemaining: number | null;
    employeeCount: number;
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6 py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {business?.status === 'trial' &&
                        trialDaysRemaining !== null && (
                            <div className="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                                You're on a free trial &mdash;{' '}
                                <strong>
                                    {trialDaysRemaining} day
                                    {trialDaysRemaining === 1 ? '' : 's'}
                                </strong>{' '}
                                remaining.{' '}
                                <Link
                                    href={route('settings.subscription.show')}
                                    className="underline"
                                >
                                    View plans
                                </Link>
                                .
                            </div>
                        )}

                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <Card title="Business">
                            <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {business?.name ?? '—'}
                            </p>
                            <p className="mt-1 text-sm capitalize text-gray-500 dark:text-gray-400">
                                {business?.business_type}
                            </p>
                        </Card>

                        <Card title="Subscription">
                            <Badge
                                variant={
                                    subscription?.status === 'trialing'
                                        ? 'info'
                                        : 'success'
                                }
                            >
                                {subscription?.status ?? 'none'}
                            </Badge>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {subscription?.plan?.name ?? 'No plan'}
                            </p>
                        </Card>

                        <Card title="Employees">
                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {employeeCount}
                            </p>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                team member{employeeCount === 1 ? '' : 's'}
                            </p>
                        </Card>

                        <Card title="Quick actions">
                            <div className="flex flex-col gap-2 text-sm">
                                <Link
                                    href={route('settings.employees.index')}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Invite an employee
                                </Link>
                                <Link
                                    href={route('settings.branches.index')}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Manage Branches
                                </Link>
                                <Link
                                    href={route('settings.roles.index')}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Manage Roles & Permissions
                                </Link>
                                <Link
                                    href={route('settings.business.edit')}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Business Settings
                                </Link>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
