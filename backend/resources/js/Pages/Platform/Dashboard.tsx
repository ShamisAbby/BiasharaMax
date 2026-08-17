import BiBadge from '@/Components/Bi/BiBadge';
import BiCard from '@/Components/Bi/BiCard';
import BiChart from '@/Components/Bi/BiChart';
import BiEmptyState from '@/Components/Bi/BiEmptyState';
import BiKpiCard, { BiKpiMetric } from '@/Components/Bi/BiKpiCard';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { useCurrency } from '@/contexts/CurrencyContext';
import PlatformLayout from '@/Layouts/PlatformLayout';
import {
    BanknotesIcon,
    BoltIcon,
    BuildingOffice2Icon,
    BuildingStorefrontIcon,
    ChartBarIcon,
    CircleStackIcon,
    ClockIcon,
    CpuChipIcon,
    CreditCardIcon,
    DocumentTextIcon,
    KeyIcon,
    MegaphoneIcon,
    RectangleStackIcon,
    ShieldCheckIcon,
    SparklesIcon,
    UserGroupIcon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface Overview {
    total_businesses: number;
    active_businesses: number;
    inactive_businesses: number;
    trial_accounts: number;
    total_users: number;
    total_superadmins: number;
    active_subscriptions: number;
    expired_subscriptions: number;
    total_products: number;
    system_health: {
        database: boolean;
        redis: boolean;
        redis_in_use: boolean;
        queue_connection: string;
    };
}

interface MonthlyPoint {
    label: string;
    count: number;
}

interface QueueSnapshot {
    pending_jobs: number | null;
    failed_jobs: number;
    horizon_available: boolean;
}

interface Kpis {
    total_businesses: BiKpiMetric;
    active_businesses: BiKpiMetric;
    inactive_businesses: BiKpiMetric;
    trial_businesses: BiKpiMetric;
    active_subscriptions: BiKpiMetric;
    monthly_revenue: BiKpiMetric;
    mrr: BiKpiMetric;
    arr: BiKpiMetric;
    total_users: BiKpiMetric;
    storage_usage: BiKpiMetric;
    cpu_usage: BiKpiMetric;
    memory_usage: BiKpiMetric;
    platform_health: BiKpiMetric;
    ai_health_score: BiKpiMetric;
}

interface BusinessPulse {
    platform_health_score: number;
    revenue_change_percent: number | null;
    new_businesses_7d: number;
    new_subscriptions_7d: number;
    businesses_at_risk: number;
    inactive_businesses: number;
    security_score: number;
    security_signals: { label: string; count: number; deduction: number }[];
    system_health_label: string;
    ai_recommendations: {
        id: string;
        title: string;
        summary: string | null;
        created_at: string;
    }[];
    ai_configured: boolean;
}

interface ActivityRow {
    id: string;
    actor_name: string;
    actor_type: string | null;
    module: string | null;
    action: string;
    auditable_type: string | null;
    business_name: string | null;
    created_at: string;
}

interface RevenuePoint {
    label: string;
    amount: number;
}

interface PaymentMethodRow {
    payment_method: string;
    total: number;
    count: number;
}

function greeting(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
}

const ACTION_LABEL: Record<string, string> = {
    created: 'created a',
    updated: 'updated a',
    deleted: 'deleted a',
};

function activityLine(row: ActivityRow): string {
    const verb = ACTION_LABEL[row.action] ?? row.action;
    const subject = row.auditable_type ?? 'record';
    return `${verb} ${subject}`;
}

export default function PlatformDashboard({
    overview,
    businessRegistrationTrend,
    subscriptionGrowth,
    topBusinessTypes,
    countryDistribution,
    subscriptionStatusBreakdown,
    queueSnapshot,
    kpis,
    businessPulse,
    liveActivity,
    revenueTrend,
    paymentMethods,
    platformVersion,
}: {
    overview: Overview;
    businessRegistrationTrend: MonthlyPoint[];
    subscriptionGrowth: MonthlyPoint[];
    topBusinessTypes: MonthlyPoint[];
    countryDistribution: MonthlyPoint[];
    subscriptionStatusBreakdown: MonthlyPoint[];
    queueSnapshot: QueueSnapshot;
    kpis: Kpis;
    businessPulse: BusinessPulse;
    liveActivity: ActivityRow[];
    revenueTrend: RevenuePoint[];
    paymentMethods: PaymentMethodRow[];
    platformVersion: string;
}) {
    const { platformAuth } = usePage().props as unknown as {
        platformAuth: { user?: { name: string } };
    };
    const { notify } = useBiNotification();
    const firstName = platformAuth?.user?.name?.split(' ')[0] ?? 'there';

    const today = new Date().toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });

    const runBackup = () => {
        router.post(
            route('platform.system.backups.run'),
            { type: 'database' },
            {
                onSuccess: () => notify('Backup started.', 'success'),
            },
        );
    };

    const quickActions = [
        {
            key: 'business',
            label: 'New Business',
            icon: <BuildingOffice2Icon className="h-5 w-5" />,
            href: route('platform.businesses.index'),
            color: 'bg-indigo-600',
        },
        {
            key: 'user',
            label: 'New User',
            icon: <UserPlusIcon className="h-5 w-5" />,
            href: route('platform.users.index'),
            color: 'bg-purple-600',
        },
        {
            key: 'subscription',
            label: 'New Subscription',
            icon: <CreditCardIcon className="h-5 w-5" />,
            href: route('platform.subscriptions.dashboard'),
            color: 'bg-cyan-600',
        },
        {
            key: 'license',
            label: 'New License',
            icon: <KeyIcon className="h-5 w-5" />,
            href: route('platform.licenses.dashboard'),
            color: 'bg-rose-600',
        },
        {
            key: 'broadcast',
            label: 'Broadcast',
            icon: <MegaphoneIcon className="h-5 w-5" />,
            href: route('platform.operations.notifications.index'),
            color: 'bg-amber-600',
        },
        {
            key: 'backup',
            label: 'Backup Now',
            icon: <CircleStackIcon className="h-5 w-5" />,
            onClick: runBackup,
            color: 'bg-emerald-600',
        },
        {
            key: 'report',
            label: 'Generate Report',
            icon: <DocumentTextIcon className="h-5 w-5" />,
            href: route('platform.finance.reports.index'),
            color: 'bg-blue-600',
        },
        {
            key: 'gateway',
            label: 'Payment Gateway',
            icon: <BanknotesIcon className="h-5 w-5" />,
            href: route('platform.finance.gateways.index'),
            color: 'bg-teal-600',
        },
        {
            key: 'templates',
            label: 'Manage Templates',
            icon: <RectangleStackIcon className="h-5 w-5" />,
            href: route('platform.operations.website-templates.index'),
            color: 'bg-fuchsia-600',
        },
    ];

    return (
        <PlatformLayout>
            <Head title="Platform Dashboard" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {greeting()}, {firstName} 👋
                        </h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Welcome back to BiasharaMax. Here&apos;s what&apos;s
                            happening across your platform today.
                        </p>
                        <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">
                            {today} · BiasharaMax v{platformVersion}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <BiBadge
                            variant={
                                businessPulse.system_health_label ===
                                    'Excellent' ||
                                businessPulse.system_health_label === 'Good'
                                    ? 'success'
                                    : 'warning'
                            }
                        >
                            Platform {businessPulse.system_health_label}
                        </BiBadge>
                        <BiBadge
                            variant={
                                overview.system_health.database
                                    ? 'success'
                                    : 'danger'
                            }
                        >
                            DB{' '}
                            {overview.system_health.database
                                ? 'Online'
                                : 'Offline'}
                        </BiBadge>
                        {/*
                            Neutral, not green, where Redis isn't
                            configured — this deployment runs cache,
                            session and queue on the database. A green
                            "Online" would claim a service that isn't
                            installed; a red "Offline" reads as an
                            outage. Neither is true.
                        */}
                        <BiBadge
                            variant={
                                !overview.system_health.redis_in_use
                                    ? 'neutral'
                                    : overview.system_health.redis
                                      ? 'success'
                                      : 'danger'
                            }
                        >
                            Redis{' '}
                            {!overview.system_health.redis_in_use
                                ? 'Not in use'
                                : overview.system_health.redis
                                  ? 'Online'
                                  : 'Offline'}
                        </BiBadge>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiKpiCard
                        icon={<BuildingOffice2Icon className="h-5 w-5" />}
                        iconClassName="bg-indigo-600"
                        title="Total Businesses"
                        metric={kpis.total_businesses}
                        href={route('platform.businesses.index')}
                    />
                    <BiKpiCard
                        icon={<BuildingStorefrontIcon className="h-5 w-5" />}
                        iconClassName="bg-emerald-600"
                        title="Active Businesses"
                        metric={kpis.active_businesses}
                        href={route('platform.businesses.index')}
                    />
                    <BiKpiCard
                        icon={<BuildingOffice2Icon className="h-5 w-5" />}
                        iconClassName="bg-gray-500"
                        title="Inactive Businesses"
                        metric={kpis.inactive_businesses}
                        href={route('platform.businesses.index')}
                        invertTone
                    />
                    <BiKpiCard
                        icon={<BuildingOffice2Icon className="h-5 w-5" />}
                        iconClassName="bg-blue-600"
                        title="Trial Businesses"
                        metric={kpis.trial_businesses}
                        href={route('platform.businesses.index')}
                    />

                    <BiKpiCard
                        icon={<CreditCardIcon className="h-5 w-5" />}
                        iconClassName="bg-cyan-600"
                        title="Active Subscriptions"
                        metric={kpis.active_subscriptions}
                        href={route('platform.subscriptions.dashboard')}
                    />
                    <MonetaryKpiCards kpis={kpis} />

                    <BiKpiCard
                        icon={<UsersIcon className="h-5 w-5" />}
                        iconClassName="bg-purple-600"
                        title="Total Users"
                        metric={kpis.total_users}
                        href={route('platform.users.index')}
                    />
                    <BiKpiCard
                        icon={<CircleStackIcon className="h-5 w-5" />}
                        iconClassName="bg-slate-600"
                        title="Storage Usage"
                        metric={kpis.storage_usage}
                        formatter={(v) => `${v}%`}
                        href={route('platform.system.dashboard')}
                        invertTone
                    />
                    <BiKpiCard
                        icon={<CpuChipIcon className="h-5 w-5" />}
                        iconClassName="bg-orange-600"
                        title="CPU Usage"
                        metric={kpis.cpu_usage}
                        formatter={(v) => `${v}%`}
                        href={route('platform.system.dashboard')}
                        invertTone
                    />
                    <BiKpiCard
                        icon={<CpuChipIcon className="h-5 w-5" />}
                        iconClassName="bg-red-600"
                        title="Memory Usage"
                        metric={kpis.memory_usage}
                        formatter={(v) => `${v}%`}
                        href={route('platform.system.dashboard')}
                        invertTone
                    />

                    <BiKpiCard
                        icon={<ShieldCheckIcon className="h-5 w-5" />}
                        iconClassName="bg-emerald-700"
                        title="Platform Health"
                        metric={kpis.platform_health}
                        formatter={(v) => `${v}%`}
                        href={route('platform.system.dashboard')}
                    />
                    <BiKpiCard
                        icon={<SparklesIcon className="h-5 w-5" />}
                        iconClassName="bg-pink-600"
                        title="AI Health Score"
                        metric={kpis.ai_health_score}
                        formatter={(v) => `${v}%`}
                        href={route('platform.system.ai-insights.index')}
                    />
                </div>

                <BiCard
                    title="Business Pulse"
                    description="Overall platform health at a glance"
                >
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Overall Platform Health
                            </p>
                            <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {businessPulse.platform_health_score}%
                            </p>
                            <BiBadge
                                variant={
                                    businessPulse.system_health_label ===
                                    'Excellent'
                                        ? 'success'
                                        : businessPulse.system_health_label ===
                                            'Good'
                                          ? 'info'
                                          : 'warning'
                                }
                            >
                                {businessPulse.system_health_label}
                            </BiBadge>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Revenue (vs last month)
                            </p>
                            <p
                                className={`mt-1 text-3xl font-bold ${(businessPulse.revenue_change_percent ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}
                            >
                                {businessPulse.revenue_change_percent === null
                                    ? '—'
                                    : `${businessPulse.revenue_change_percent >= 0 ? '↑' : '↓'}${Math.abs(businessPulse.revenue_change_percent)}%`}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                New Businesses (7d)
                            </p>
                            <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                +{businessPulse.new_businesses_7d}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                New Subscriptions (7d)
                            </p>
                            <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                +{businessPulse.new_subscriptions_7d}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Businesses At Risk
                            </p>
                            <p
                                className={`mt-1 text-3xl font-bold ${businessPulse.businesses_at_risk > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100'}`}
                            >
                                {businessPulse.businesses_at_risk}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Inactive Businesses
                            </p>
                            <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {businessPulse.inactive_businesses}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Security Score
                            </p>
                            <p
                                className={`mt-1 text-3xl font-bold ${businessPulse.security_score >= 90 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}
                            >
                                {businessPulse.security_score}%
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                System Health
                            </p>
                            <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {businessPulse.system_health_label}
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 border-t border-gray-100 pt-6 dark:border-gray-700">
                        <p className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            <SparklesIcon className="h-4 w-4 text-pink-500" />
                            AI Recommendations
                        </p>
                        {businessPulse.ai_recommendations.length > 0 ? (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {businessPulse.ai_recommendations.map((rec) => (
                                    <div
                                        key={rec.id}
                                        className="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                    >
                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {rec.title}
                                        </p>
                                        <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {rec.summary}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {businessPulse.ai_configured
                                    ? 'No AI recommendations generated yet.'
                                    : 'AI recommendations are not active yet — '}
                                {!businessPulse.ai_configured && (
                                    <Link
                                        href={route(
                                            'platform.system.integrations.index',
                                        )}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        enable an AI integration
                                    </Link>
                                )}
                                {!businessPulse.ai_configured && '.'}
                            </p>
                        )}
                    </div>
                </BiCard>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard
                        title="Live Activity"
                        description="Latest actions across the platform"
                        className="lg:col-span-2"
                        padded={false}
                    >
                        {liveActivity.length > 0 ? (
                            <div className="max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                                {liveActivity.map((row) => (
                                    <div
                                        key={row.id}
                                        className="flex items-start gap-3 px-6 py-3"
                                    >
                                        <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                                            <ClockIcon className="h-4 w-4" />
                                        </span>
                                        <div className="flex-1 text-sm">
                                            <p className="text-gray-900 dark:text-gray-100">
                                                <span className="font-medium">
                                                    {row.actor_name}
                                                </span>{' '}
                                                {activityLine(row)}
                                                {row.business_name && (
                                                    <>
                                                        {' '}
                                                        for{' '}
                                                        <span className="font-medium">
                                                            {row.business_name}
                                                        </span>
                                                    </>
                                                )}
                                            </p>
                                            <p className="text-xs text-gray-400 dark:text-gray-500">
                                                {row.module ?? 'Platform'} ·{' '}
                                                {new Date(
                                                    row.created_at,
                                                ).toLocaleString()}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <BiEmptyState
                                icon={<ClockIcon className="h-6 w-6" />}
                                title="No activity yet"
                                description="Actions across the platform will show up here in real time."
                            />
                        )}
                        <div className="border-t border-gray-100 px-6 py-3 dark:border-gray-700">
                            <Link
                                href={route('platform.audit-logs.index')}
                                className="text-sm font-medium text-indigo-600 hover:underline"
                            >
                                View all activity →
                            </Link>
                        </div>
                    </BiCard>

                    <BiCard title="Quick Actions">
                        <div className="grid grid-cols-2 gap-3">
                            {quickActions.map((action) =>
                                action.href ? (
                                    <Link
                                        key={action.key}
                                        href={action.href}
                                        className="flex flex-col items-start gap-2 rounded-xl border border-gray-100 p-3 text-left transition hover:border-indigo-200 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-800 dark:hover:bg-indigo-900/20"
                                    >
                                        <span
                                            className={`flex h-9 w-9 items-center justify-center rounded-lg text-white ${action.color}`}
                                        >
                                            {action.icon}
                                        </span>
                                        <span className="text-xs font-medium text-gray-700 dark:text-gray-300">
                                            {action.label}
                                        </span>
                                    </Link>
                                ) : (
                                    <button
                                        key={action.key}
                                        type="button"
                                        onClick={action.onClick}
                                        className="flex flex-col items-start gap-2 rounded-xl border border-gray-100 p-3 text-left transition hover:border-indigo-200 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-800 dark:hover:bg-indigo-900/20"
                                    >
                                        <span
                                            className={`flex h-9 w-9 items-center justify-center rounded-lg text-white ${action.color}`}
                                        >
                                            {action.icon}
                                        </span>
                                        <span className="text-xs font-medium text-gray-700 dark:text-gray-300">
                                            {action.label}
                                        </span>
                                    </button>
                                ),
                            )}
                        </div>
                    </BiCard>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard
                        title="Revenue Trend"
                        description="Last 12 months"
                        className="lg:col-span-2"
                    >
                        {revenueTrend.some((p) => p.amount > 0) ? (
                            <BiChart
                                type="line"
                                labels={revenueTrend.map((p) => p.label)}
                                datasets={[
                                    {
                                        label: 'Revenue (TZS)',
                                        data: revenueTrend.map((p) => p.amount),
                                    },
                                ]}
                                showLegend={false}
                            />
                        ) : (
                            <BiEmptyState
                                icon={<BanknotesIcon className="h-6 w-6" />}
                                title="No revenue yet"
                                description="Revenue will appear here once payments start coming in."
                                actionLabel="View payment gateways"
                                actionHref={route(
                                    'platform.finance.gateways.index',
                                )}
                            />
                        )}
                    </BiCard>

                    <BiCard title="Payment Methods">
                        {paymentMethods.length > 0 ? (
                            <BiChart
                                type="doughnut"
                                labels={paymentMethods.map(
                                    (p) => p.payment_method,
                                )}
                                datasets={[
                                    {
                                        label: 'Revenue',
                                        data: paymentMethods.map(
                                            (p) => p.total,
                                        ),
                                    },
                                ]}
                            />
                        ) : (
                            <BiEmptyState
                                icon={<CreditCardIcon className="h-6 w-6" />}
                                title="No payments yet"
                                description="Payment method breakdown appears once transactions exist."
                            />
                        )}
                    </BiCard>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard
                        title="Business Registration Trend"
                        description="Last 12 months"
                        className="lg:col-span-2"
                    >
                        <BiChart
                            type="line"
                            labels={businessRegistrationTrend.map(
                                (p) => p.label,
                            )}
                            datasets={[
                                {
                                    label: 'New businesses',
                                    data: businessRegistrationTrend.map(
                                        (p) => p.count,
                                    ),
                                },
                            ]}
                            showLegend={false}
                        />
                    </BiCard>

                    <BiCard title="Top Business Types">
                        {topBusinessTypes.length > 0 ? (
                            <BiChart
                                type="doughnut"
                                labels={topBusinessTypes.map((p) => p.label)}
                                datasets={[
                                    {
                                        label: 'Businesses',
                                        data: topBusinessTypes.map(
                                            (p) => p.count,
                                        ),
                                    },
                                ]}
                            />
                        ) : (
                            <BiEmptyState
                                icon={
                                    <BuildingOffice2Icon className="h-6 w-6" />
                                }
                                title="No businesses yet"
                                description="Create your first business to see type distribution here."
                                actionLabel="Create Business"
                                actionHref={route('platform.businesses.index')}
                            />
                        )}
                    </BiCard>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard
                        title="Subscription Growth"
                        description="Last 12 months"
                    >
                        <BiChart
                            type="bar"
                            labels={subscriptionGrowth.map((p) => p.label)}
                            datasets={[
                                {
                                    label: 'New subscriptions',
                                    data: subscriptionGrowth.map(
                                        (p) => p.count,
                                    ),
                                },
                            ]}
                            showLegend={false}
                        />
                    </BiCard>

                    <BiCard title="Country Distribution">
                        {countryDistribution.length > 0 ? (
                            <BiChart
                                type="doughnut"
                                labels={countryDistribution.map((p) => p.label)}
                                datasets={[
                                    {
                                        label: 'Businesses',
                                        data: countryDistribution.map(
                                            (p) => p.count,
                                        ),
                                    },
                                ]}
                            />
                        ) : (
                            <BiEmptyState
                                icon={<UserGroupIcon className="h-6 w-6" />}
                                title="No businesses yet"
                                description="Country distribution appears once businesses register."
                            />
                        )}
                    </BiCard>

                    <BiCard title="Subscription Status">
                        <div className="space-y-3">
                            {subscriptionStatusBreakdown.length > 0 ? (
                                subscriptionStatusBreakdown.map((row) => (
                                    <div
                                        key={row.label}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <BiBadge variant="info">
                                            {row.label}
                                        </BiBadge>
                                        <span className="font-medium text-gray-900 dark:text-gray-100">
                                            {row.count}
                                        </span>
                                    </div>
                                ))
                            ) : (
                                <BiEmptyState
                                    icon={
                                        <CreditCardIcon className="h-6 w-6" />
                                    }
                                    title="No subscriptions yet"
                                    description="Subscription status breakdown appears here once businesses subscribe."
                                />
                            )}
                        </div>
                    </BiCard>
                </div>

                <BiCard
                    title="Queue & Background Jobs"
                    description="Live Horizon-backed snapshot"
                    actions={<BoltIcon className="h-5 w-5 text-amber-500" />}
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Pending jobs
                            </p>
                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                {queueSnapshot.pending_jobs ?? '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Failed jobs
                            </p>
                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                {queueSnapshot.failed_jobs}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Horizon
                            </p>
                            <BiBadge
                                variant={
                                    queueSnapshot.horizon_available
                                        ? 'success'
                                        : 'neutral'
                                }
                            >
                                {queueSnapshot.horizon_available
                                    ? 'Available'
                                    : 'Not installed'}
                            </BiBadge>
                        </div>
                    </div>
                </BiCard>
            </div>
        </PlatformLayout>
    );
}

/**
 * Rendered as a child of PlatformLayout (not the page component itself),
 * since useCurrency()'s provider lives inside PlatformLayout — a hook
 * called directly in the page's top-level function would run before that
 * provider mounts and would never see its context.
 */
function MonetaryKpiCards({
    kpis,
}: {
    kpis: Pick<Kpis, 'monthly_revenue' | 'mrr' | 'arr'>;
}) {
    const { formatMoney } = useCurrency();

    return (
        <>
            <BiKpiCard
                icon={<BanknotesIcon className="h-5 w-5" />}
                iconClassName="bg-amber-600"
                title="Monthly Revenue"
                metric={kpis.monthly_revenue}
                formatter={formatMoney}
                href={route('platform.finance.dashboard')}
            />
            <BiKpiCard
                icon={<ChartBarIcon className="h-5 w-5" />}
                iconClassName="bg-violet-600"
                title="MRR"
                metric={kpis.mrr}
                formatter={formatMoney}
                href={route('platform.finance.dashboard')}
            />
            <BiKpiCard
                icon={<ChartBarIcon className="h-5 w-5" />}
                iconClassName="bg-purple-700"
                title="ARR"
                metric={kpis.arr}
                formatter={formatMoney}
                href={route('platform.finance.dashboard')}
            />
        </>
    );
}
