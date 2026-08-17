import BiBadge from '@/Components/Bi/BiBadge';
import BiCard from '@/Components/Bi/BiCard';
import BiStatsCard from '@/Components/Bi/BiStatsCard';
import PlatformLayout from '@/Layouts/PlatformLayout';
import {
    BellAlertIcon,
    CpuChipIcon,
    ExclamationTriangleIcon,
    LifebuoyIcon,
    LockClosedIcon,
    RectangleStackIcon,
    ServerStackIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';
import { Head } from '@inertiajs/react';

interface ActivityRow {
    id: string;
    module: string | null;
    action: string;
    auditable_type: string | null;
    business_name: string | null;
    risk_level: string;
    created_at: string;
}

const RISK_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral'
> = {
    low: 'neutral',
    normal: 'success',
    elevated: 'warning',
    high: 'danger',
};

export default function OperationsDashboard({
    websiteTemplates,
    notificationsSentToday,
    openSupportTickets,
    criticalSecurityAlerts,
    failedLogins24h,
    activeSessions,
    serverHealth,
    platformUptime,
    recentActivities,
}: {
    websiteTemplates: { total: number; published: number };
    notificationsSentToday: number;
    openSupportTickets: number;
    criticalSecurityAlerts: number;
    failedLogins24h: number;
    activeSessions: number;
    serverHealth: {
        cpu_usage: number | null;
        memory_usage: number | null;
        disk_usage: number | null;
        health_score: number | null;
    };
    platformUptime: string | null;
    recentActivities: ActivityRow[];
}) {
    return (
        <PlatformLayout>
            <Head title="Operations Dashboard" />

            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiStatsCard
                        icon={<RectangleStackIcon className="h-5 w-5" />}
                        iconClassName="bg-indigo-600"
                        title="Website Templates"
                        value={websiteTemplates.total}
                        delta={`${websiteTemplates.published} published`}
                        deltaTone="neutral"
                    />
                    <BiStatsCard
                        icon={<BellAlertIcon className="h-5 w-5" />}
                        iconClassName="bg-blue-600"
                        title="Notifications Sent Today"
                        value={notificationsSentToday}
                    />
                    <BiStatsCard
                        icon={<LifebuoyIcon className="h-5 w-5" />}
                        iconClassName="bg-amber-600"
                        title="Open Support Tickets"
                        value={openSupportTickets}
                    />
                    <BiStatsCard
                        icon={<ExclamationTriangleIcon className="h-5 w-5" />}
                        iconClassName="bg-red-600"
                        title="Critical Security Alerts"
                        value={criticalSecurityAlerts}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiStatsCard
                        icon={<LockClosedIcon className="h-5 w-5" />}
                        iconClassName="bg-gray-600"
                        title="Failed Logins (24h)"
                        value={failedLogins24h}
                    />
                    <BiStatsCard
                        icon={<UsersIcon className="h-5 w-5" />}
                        iconClassName="bg-emerald-600"
                        title="Active Sessions"
                        value={activeSessions}
                    />
                    <BiStatsCard
                        icon={<ServerStackIcon className="h-5 w-5" />}
                        iconClassName="bg-purple-600"
                        title="System Health Score"
                        value={serverHealth.health_score ?? 0}
                        formatter={(v) => `${v}%`}
                    />
                    <BiStatsCard
                        icon={<CpuChipIcon className="h-5 w-5" />}
                        iconClassName="bg-blue-600"
                        title="CPU Usage"
                        value={serverHealth.cpu_usage ?? 0}
                        formatter={(v) => `${v}%`}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard
                        title="Server Health"
                        description={platformUptime ?? undefined}
                    >
                        <dl className="space-y-2 text-sm">
                            <Row
                                label="CPU"
                                value={`${serverHealth.cpu_usage ?? '—'}%`}
                            />
                            <Row
                                label="Memory"
                                value={`${serverHealth.memory_usage ?? '—'}%`}
                            />
                            <Row
                                label="Disk"
                                value={`${serverHealth.disk_usage ?? '—'}%`}
                            />
                        </dl>
                    </BiCard>

                    <BiCard title="Recent Activities" className="lg:col-span-2">
                        {recentActivities.length > 0 ? (
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {recentActivities.map((activity) => (
                                    <div
                                        key={activity.id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <div>
                                            <p className="text-gray-900 dark:text-gray-100">
                                                {activity.module ?? '—'} ·{' '}
                                                {activity.action}{' '}
                                                {activity.auditable_type}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {activity.business_name ??
                                                    'Platform'}
                                            </p>
                                        </div>
                                        <BiBadge
                                            variant={
                                                RISK_VARIANT[
                                                    activity.risk_level
                                                ] ?? 'neutral'
                                            }
                                        >
                                            {activity.risk_level}
                                        </BiBadge>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No recent activity.
                            </p>
                        )}
                    </BiCard>
                </div>
            </div>
        </PlatformLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between">
            <dt className="text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="font-medium text-gray-900 dark:text-gray-100">
                {value}
            </dd>
        </div>
    );
}
