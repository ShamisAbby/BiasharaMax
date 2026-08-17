import BiBadge from '@/Components/Bi/BiBadge';
import BiCard from '@/Components/Bi/BiCard';
import BiChart from '@/Components/Bi/BiChart';
import BiStatsCard from '@/Components/Bi/BiStatsCard';
import PlatformLayout from '@/Layouts/PlatformLayout';
import {
    CircleStackIcon,
    CpuChipIcon,
    ServerStackIcon,
    ShieldCheckIcon,
} from '@heroicons/react/24/outline';

interface TrendPoint {
    cpu_usage: number | null;
    memory_usage: number | null;
    disk_usage: number | null;
    health_score: number | null;
    recorded_at: string;
}

interface BackupRow {
    id: string;
    type: string;
    status: string;
    started_at: string;
    completed_at: string | null;
}

export default function MonitoringIndex({
    live,
    trend,
    backups,
}: {
    live: {
        cpu_usage: number | null;
        memory_usage: number | null;
        disk_usage: number | null;
        queue_pending: number | null;
        queue_failed: number;
        db_response_time_ms: number | null;
        redis_status: string;
        horizon_status: string;
        health_score: number;
        platform_version: string;
        uptime: string | null;
    };
    trend: TrendPoint[];
    backups: BackupRow[];
}) {
    return (
        <PlatformLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        System Monitoring
                    </h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {live.uptime ?? 'Uptime unavailable'} · v
                        {live.platform_version}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiStatsCard
                        icon={<CpuChipIcon className="h-5 w-5" />}
                        iconClassName="bg-blue-600"
                        title="CPU Usage"
                        value={live.cpu_usage ?? 0}
                        formatter={(v) => `${v}%`}
                    />
                    <BiStatsCard
                        icon={<ServerStackIcon className="h-5 w-5" />}
                        iconClassName="bg-purple-600"
                        title="Memory Usage"
                        value={live.memory_usage ?? 0}
                        formatter={(v) => `${v}%`}
                    />
                    <BiStatsCard
                        icon={<CircleStackIcon className="h-5 w-5" />}
                        iconClassName="bg-amber-600"
                        title="Disk Usage"
                        value={live.disk_usage ?? 0}
                        formatter={(v) => `${v}%`}
                    />
                    <BiStatsCard
                        icon={<ShieldCheckIcon className="h-5 w-5" />}
                        iconClassName="bg-emerald-600"
                        title="Health Score"
                        value={live.health_score}
                        formatter={(v) => `${v}%`}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiCard title="Database">
                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {live.db_response_time_ms ?? '—'} ms
                        </p>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Response time
                        </p>
                    </BiCard>
                    <BiCard title="Redis">
                        <BiBadge
                            variant={
                                live.redis_status === 'online'
                                    ? 'success'
                                    : 'danger'
                            }
                        >
                            {live.redis_status}
                        </BiBadge>
                    </BiCard>
                    <BiCard title="Horizon / Queue Workers">
                        <BiBadge
                            variant={
                                live.horizon_status === 'running'
                                    ? 'success'
                                    : 'warning'
                            }
                        >
                            {live.horizon_status}
                        </BiBadge>
                    </BiCard>
                    <BiCard title="Queue">
                        <p className="text-sm text-gray-700 dark:text-gray-300">
                            {live.queue_pending ?? '—'} pending ·{' '}
                            {live.queue_failed} failed
                        </p>
                    </BiCard>
                </div>

                <BiCard title="24-Hour Trend">
                    {trend.length > 0 ? (
                        <BiChart
                            type="line"
                            labels={trend.map((p) =>
                                new Date(p.recorded_at).toLocaleTimeString(),
                            )}
                            datasets={[
                                {
                                    label: 'CPU %',
                                    data: trend.map((p) => p.cpu_usage ?? 0),
                                },
                                {
                                    label: 'Memory %',
                                    data: trend.map((p) => p.memory_usage ?? 0),
                                },
                                {
                                    label: 'Health Score',
                                    data: trend.map((p) => p.health_score ?? 0),
                                },
                            ]}
                        />
                    ) : (
                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No historical data yet — snapshots are recorded
                            every 5 minutes.
                        </p>
                    )}
                </BiCard>

                <BiCard title="Backup Status">
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {backups.map((backup) => (
                            <div
                                key={backup.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <span className="text-gray-900 dark:text-gray-100">
                                    {backup.type}
                                </span>
                                <BiBadge
                                    variant={
                                        backup.status === 'success'
                                            ? 'success'
                                            : backup.status === 'failed'
                                              ? 'danger'
                                              : 'warning'
                                    }
                                >
                                    {backup.status}
                                </BiBadge>
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    {new Date(
                                        backup.started_at,
                                    ).toLocaleString()}
                                </span>
                            </div>
                        ))}
                        {backups.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No backups recorded yet.
                            </p>
                        )}
                    </div>
                </BiCard>
            </div>
        </PlatformLayout>
    );
}
