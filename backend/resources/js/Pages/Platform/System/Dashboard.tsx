import BiBadge from '@/Components/Bi/BiBadge';
import BiCard from '@/Components/Bi/BiCard';
import BiStatsCard from '@/Components/Bi/BiStatsCard';
import PlatformLayout from '@/Layouts/PlatformLayout';
import {
    CircleStackIcon,
    PuzzlePieceIcon,
    ServerStackIcon,
    ShieldCheckIcon,
} from '@heroicons/react/24/outline';

interface BackupRow {
    id: string;
    type: string;
    status: string;
    started_at: string;
}

interface IntegrationRow {
    id: string;
    name: string;
    is_enabled: boolean;
    last_test_result: string | null;
}

interface RecommendationRow {
    id: string;
    title: string;
    summary: string | null;
    created_at: string;
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral'
> = {
    success: 'success',
    running: 'warning',
    failed: 'danger',
    configured: 'success',
    not_configured: 'neutral',
    healthy: 'success',
    attention_needed: 'warning',
    online: 'success',
    offline: 'danger',
};

export default function SystemDashboard({
    platformVersion,
    laravelVersion,
    platformUptime,
    backupStatus,
    storageUsage,
    securityStatus,
    databaseStatus,
    integrationStatus,
    emailStatus,
    smsStatus,
    paymentStatus,
    recentBackups,
    recentIntegrations,
    recentAiRecommendations,
}: {
    platformVersion: string;
    laravelVersion: string;
    platformUptime: string | null;
    backupStatus: { status: string; started_at: string } | null;
    storageUsage: number | null;
    securityStatus: string;
    databaseStatus: string;
    integrationStatus: { total: number; enabled: number };
    emailStatus: string;
    smsStatus: string;
    paymentStatus: string;
    recentBackups: BackupRow[];
    recentIntegrations: IntegrationRow[];
    recentAiRecommendations: RecommendationRow[];
}) {
    return (
        <PlatformLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        System Dashboard
                    </h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        BiasharaMax v{platformVersion} · Laravel{' '}
                        {laravelVersion} ·{' '}
                        {platformUptime ?? 'Uptime unavailable'}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiStatsCard
                        icon={<ServerStackIcon className="h-5 w-5" />}
                        iconClassName="bg-indigo-600"
                        title="Storage Usage"
                        value={storageUsage ?? 0}
                        formatter={(v) => `${v}%`}
                    />
                    <BiStatsCard
                        icon={<ShieldCheckIcon className="h-5 w-5" />}
                        iconClassName={
                            securityStatus === 'healthy'
                                ? 'bg-emerald-600'
                                : 'bg-amber-600'
                        }
                        title="Security Status"
                        value={
                            securityStatus === 'healthy'
                                ? 'Healthy'
                                : 'Needs Attention'
                        }
                    />
                    <BiStatsCard
                        icon={<CircleStackIcon className="h-5 w-5" />}
                        iconClassName={
                            databaseStatus === 'online'
                                ? 'bg-emerald-600'
                                : 'bg-red-600'
                        }
                        title="Database Status"
                        value={
                            databaseStatus === 'online' ? 'Online' : 'Offline'
                        }
                    />
                    <BiStatsCard
                        icon={<PuzzlePieceIcon className="h-5 w-5" />}
                        iconClassName="bg-purple-600"
                        title="Integrations"
                        value={integrationStatus.enabled}
                        delta={`${integrationStatus.total} total`}
                        deltaTone="neutral"
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiCard title="Backup Status">
                        {backupStatus ? (
                            <BiBadge
                                variant={
                                    STATUS_VARIANT[backupStatus.status] ??
                                    'neutral'
                                }
                            >
                                {backupStatus.status}
                            </BiBadge>
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                No backups yet
                            </p>
                        )}
                    </BiCard>
                    <BiCard title="Email Status">
                        <BiBadge
                            variant={STATUS_VARIANT[emailStatus] ?? 'neutral'}
                        >
                            {emailStatus.replace('_', ' ')}
                        </BiBadge>
                    </BiCard>
                    <BiCard title="SMS Status">
                        <BiBadge
                            variant={STATUS_VARIANT[smsStatus] ?? 'neutral'}
                        >
                            {smsStatus.replace('_', ' ')}
                        </BiBadge>
                    </BiCard>
                    <BiCard title="Payment Status">
                        <BiBadge
                            variant={STATUS_VARIANT[paymentStatus] ?? 'neutral'}
                        >
                            {paymentStatus.replace('_', ' ')}
                        </BiBadge>
                    </BiCard>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard title="Recent Backups">
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {recentBackups.map((backup) => (
                                <div
                                    key={backup.id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <span className="text-gray-900 dark:text-gray-100">
                                        {backup.type}
                                    </span>
                                    <BiBadge
                                        variant={
                                            STATUS_VARIANT[backup.status] ??
                                            'neutral'
                                        }
                                    >
                                        {backup.status}
                                    </BiBadge>
                                </div>
                            ))}
                            {recentBackups.length === 0 && (
                                <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No backups yet.
                                </p>
                            )}
                        </div>
                    </BiCard>

                    <BiCard title="Recent Integrations">
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {recentIntegrations.map((integration) => (
                                <div
                                    key={integration.id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <span className="text-gray-900 dark:text-gray-100">
                                        {integration.name}
                                    </span>
                                    <BiBadge
                                        variant={
                                            integration.is_enabled
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {integration.is_enabled
                                            ? 'Enabled'
                                            : 'Disabled'}
                                    </BiBadge>
                                </div>
                            ))}
                            {recentIntegrations.length === 0 && (
                                <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No integrations yet.
                                </p>
                            )}
                        </div>
                    </BiCard>

                    <BiCard title="Recent AI Recommendations">
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {recentAiRecommendations.map((rec) => (
                                <div key={rec.id} className="py-2 text-sm">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {rec.title}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {rec.summary}
                                    </p>
                                </div>
                            ))}
                            {recentAiRecommendations.length === 0 && (
                                <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No AI recommendations yet.
                                </p>
                            )}
                        </div>
                    </BiCard>
                </div>
            </div>
        </PlatformLayout>
    );
}
