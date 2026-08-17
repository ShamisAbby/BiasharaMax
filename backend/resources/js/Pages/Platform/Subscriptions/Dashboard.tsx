import BiCard from '@/Components/Bi/BiCard';
import BiChart from '@/Components/Bi/BiChart';
import BiStatsCard from '@/Components/Bi/BiStatsCard';
import PlatformSubscriptionsLayout from '@/Layouts/PlatformSubscriptionsLayout';
import { formatCurrency } from '@/lib/currency';
import {
    BanknotesIcon,
    CalendarIcon,
    ClockIcon,
    UserGroupIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';

interface RevenueSummary {
    total: number;
    this_month: number;
    this_year: number;
}

interface TrialSummary {
    active: number;
    ending_in_3_days: number;
    ending_in_7_days: number;
}

interface ExpiringSubscriber {
    id: string;
    business_name: string;
    plan_name: string;
    current_period_end: string;
}

export default function SubscriptionsDashboard({
    revenue,
    trial,
    subscribers,
    monthlyRevenue,
    expiringSoon,
}: {
    revenue: RevenueSummary;
    trial: TrialSummary;
    subscribers: Record<string, number>;
    monthlyRevenue: Array<{ label: string; amount: number }>;
    expiringSoon: ExpiringSubscriber[];
}) {
    return (
        <PlatformSubscriptionsLayout title="Dashboard">
            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiStatsCard
                        icon={<BanknotesIcon className="h-5 w-5" />}
                        iconClassName="bg-emerald-600"
                        title="Total Revenue"
                        value={revenue.total}
                        formatter={(v) => formatCurrency(v)}
                    />
                    <BiStatsCard
                        icon={<BanknotesIcon className="h-5 w-5" />}
                        iconClassName="bg-indigo-600"
                        title="Revenue This Month"
                        value={revenue.this_month}
                        formatter={(v) => formatCurrency(v)}
                    />
                    <BiStatsCard
                        icon={<ClockIcon className="h-5 w-5" />}
                        iconClassName="bg-blue-600"
                        title="Active Trials"
                        value={trial.active}
                        delta={`${trial.ending_in_3_days} ending in 3 days`}
                        deltaTone={
                            trial.ending_in_3_days > 0 ? 'warning' : 'neutral'
                        }
                    />
                    <BiStatsCard
                        icon={<UserGroupIcon className="h-5 w-5" />}
                        iconClassName="bg-purple-600"
                        title="Active Subscribers"
                        value={subscribers.active ?? 0}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard
                        title="Monthly Revenue"
                        description="Last 12 months"
                        className="lg:col-span-2"
                    >
                        <BiChart
                            type="bar"
                            labels={monthlyRevenue.map((p) => p.label)}
                            datasets={[
                                {
                                    label: 'Revenue',
                                    data: monthlyRevenue.map((p) => p.amount),
                                },
                            ]}
                            showLegend={false}
                        />
                    </BiCard>

                    <BiCard title="Subscribers by Status">
                        {Object.keys(subscribers).length > 0 ? (
                            <BiChart
                                type="doughnut"
                                labels={Object.keys(subscribers).map(
                                    (key) =>
                                        key.charAt(0).toUpperCase() +
                                        key.slice(1),
                                )}
                                datasets={[
                                    {
                                        label: 'Subscribers',
                                        data: Object.values(subscribers),
                                    },
                                ]}
                            />
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No subscribers yet.
                            </p>
                        )}
                    </BiCard>
                </div>

                <BiCard
                    title="Renewals Due Soon"
                    description="Active subscriptions expiring within 14 days"
                >
                    {expiringSoon.length > 0 ? (
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {expiringSoon.map((subscriber) => (
                                <div
                                    key={subscriber.id}
                                    className="flex items-center justify-between py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                            {subscriber.business_name}
                                        </p>
                                        <p className="text-gray-500 dark:text-gray-400">
                                            {subscriber.plan_name}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                        <CalendarIcon className="h-4 w-4" />
                                        {new Date(
                                            subscriber.current_period_end,
                                        ).toLocaleDateString()}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No renewals due in the next 14 days.
                        </p>
                    )}

                    <Link
                        href={route('platform.subscriptions.subscribers.index')}
                        className="mt-4 inline-block text-sm font-medium text-indigo-600 hover:underline"
                    >
                        View all subscribers →
                    </Link>
                </BiCard>
            </div>
        </PlatformSubscriptionsLayout>
    );
}
