import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import BiStatsCard from '@/Components/Bi/BiStatsCard';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import {
    ExclamationTriangleIcon,
    LockClosedIcon,
    NoSymbolIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';
import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface FailedLogin {
    id: string;
    email: string;
    guard: string;
    ip_address: string;
    reason: string | null;
    created_at: string;
}

interface BlockedIpRow {
    id: string;
    ip_address: string;
    reason: string | null;
    is_permanent: boolean;
    is_active: boolean;
}

interface LockoutRow {
    id: string;
    lockable_type: string;
    lockable_id: string;
    reason: string | null;
    locked_at: string;
    is_active: boolean;
}

interface AlertRow {
    id: string;
    type: string;
    severity: string;
    description: string;
    is_resolved: boolean;
    created_at: string;
}

interface SessionRow {
    id: string;
    user_name: string | null;
    user_type: string | null;
    ip_address: string | null;
    last_activity: number;
}

const SEVERITY_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral'
> = {
    low: 'neutral',
    medium: 'warning',
    high: 'danger',
    critical: 'danger',
};

export default function SecurityIndex({
    failedLogins,
    blockedIps,
    lockouts,
    alerts,
    activeSessions,
    sessionsTracked,
    sessionDriver,
    summary,
}: {
    failedLogins: FailedLogin[];
    blockedIps: BlockedIpRow[];
    lockouts: LockoutRow[];
    alerts: AlertRow[];
    activeSessions: SessionRow[];
    /**
     * Whether sessions can be listed at all. False under a Redis or file
     * session driver, where an empty list means "cannot see" rather than
     * "nobody is signed in" — a distinction that matters a great deal on
     * a security screen.
     */
    sessionsTracked: boolean;
    sessionDriver: string;
    summary: {
        failed_logins_24h: number;
        blocked_ips_count: number;
        active_lockouts_count: number;
        unresolved_alerts_count: number;
        active_sessions_count: number;
    };
}) {
    const { notify } = useBiNotification();
    const [blocking, setBlocking] = useState(false);
    const [ipAddress, setIpAddress] = useState('');
    const [reason, setReason] = useState('');

    const submitBlock = (e: FormEvent) => {
        e.preventDefault();

        router.post(
            route('platform.operations.security.blocked-ips.store'),
            { ip_address: ipAddress, reason },
            {
                onSuccess: () => {
                    setBlocking(false);
                    setIpAddress('');
                    setReason('');
                    notify('IP blocked.', 'success');
                },
            },
        );
    };

    const unblock = (ip: BlockedIpRow) => {
        router.delete(
            route('platform.operations.security.blocked-ips.destroy', ip.id),
            {
                onSuccess: () => notify('IP unblocked.', 'success'),
            },
        );
    };

    const unlock = (lockout: LockoutRow) => {
        router.post(
            route('platform.operations.security.lockouts.unlock', lockout.id),
            {},
            {
                onSuccess: () => notify('Account unlocked.', 'success'),
            },
        );
    };

    const resolveAlert = (alert: AlertRow) => {
        router.post(
            route('platform.operations.security.alerts.resolve', alert.id),
            {},
            {
                onSuccess: () => notify('Alert resolved.', 'success'),
            },
        );
    };

    return (
        <PlatformLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Security Center
                    </h1>
                    <BiButton onClick={() => setBlocking(true)}>
                        Block an IP
                    </BiButton>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <BiStatsCard
                        icon={<LockClosedIcon className="h-5 w-5" />}
                        iconClassName="bg-red-600"
                        title="Failed Logins (24h)"
                        value={summary.failed_logins_24h}
                    />
                    <BiStatsCard
                        icon={<NoSymbolIcon className="h-5 w-5" />}
                        iconClassName="bg-gray-600"
                        title="Blocked IPs"
                        value={summary.blocked_ips_count}
                    />
                    <BiStatsCard
                        icon={<LockClosedIcon className="h-5 w-5" />}
                        iconClassName="bg-amber-600"
                        title="Active Lockouts"
                        value={summary.active_lockouts_count}
                    />
                    <BiStatsCard
                        icon={<ExclamationTriangleIcon className="h-5 w-5" />}
                        iconClassName="bg-orange-600"
                        title="Unresolved Alerts"
                        value={summary.unresolved_alerts_count}
                    />
                    <BiStatsCard
                        icon={<UsersIcon className="h-5 w-5" />}
                        iconClassName="bg-emerald-600"
                        title="Active Sessions"
                        value={summary.active_sessions_count}
                    />
                </div>

                <BiCard title="Security Alerts">
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {alerts.map((alert) => (
                            <div
                                key={alert.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <div>
                                    <p className="text-gray-900 dark:text-gray-100">
                                        {alert.description}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {new Date(
                                            alert.created_at,
                                        ).toLocaleString()}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <BiBadge
                                        variant={
                                            SEVERITY_VARIANT[alert.severity] ??
                                            'neutral'
                                        }
                                    >
                                        {alert.severity}
                                    </BiBadge>
                                    {!alert.is_resolved && (
                                        <button
                                            onClick={() => resolveAlert(alert)}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            Resolve
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                        {alerts.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No security alerts.
                            </p>
                        )}
                    </div>
                </BiCard>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BiCard title="Blocked IPs">
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {blockedIps.map((ip) => (
                                <div
                                    key={ip.id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <div>
                                        <p className="font-mono text-gray-900 dark:text-gray-100">
                                            {ip.ip_address}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            {ip.reason ?? 'No reason given'}
                                        </p>
                                    </div>
                                    <button
                                        onClick={() => unblock(ip)}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        Unblock
                                    </button>
                                </div>
                            ))}
                            {blockedIps.length === 0 && (
                                <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No blocked IPs.
                                </p>
                            )}
                        </div>
                    </BiCard>

                    <BiCard title="Account Lockouts">
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {lockouts.map((lockout) => (
                                <div
                                    key={lockout.id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <div>
                                        <p className="text-gray-900 dark:text-gray-100">
                                            {lockout.lockable_type}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            {lockout.reason ??
                                                'No reason given'}
                                        </p>
                                    </div>
                                    {lockout.is_active && (
                                        <button
                                            onClick={() => unlock(lockout)}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            Unlock
                                        </button>
                                    )}
                                </div>
                            ))}
                            {lockouts.length === 0 && (
                                <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No lockouts.
                                </p>
                            )}
                        </div>
                    </BiCard>
                </div>

                <BiCard title="Failed Login Attempts">
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {failedLogins.map((attempt) => (
                            <div
                                key={attempt.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <div>
                                    <p className="text-gray-900 dark:text-gray-100">
                                        {attempt.email} ({attempt.guard})
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {attempt.ip_address} · {attempt.reason}
                                    </p>
                                </div>
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    {new Date(
                                        attempt.created_at,
                                    ).toLocaleString()}
                                </span>
                            </div>
                        ))}
                        {failedLogins.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No failed login attempts.
                            </p>
                        )}
                    </div>
                </BiCard>

                <BiCard
                    title="Active Sessions"
                    description="Active in the last 30 minutes"
                >
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {activeSessions.map((session) => (
                            <div
                                key={session.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <div>
                                    <p className="text-gray-900 dark:text-gray-100">
                                        {session.user_name ?? 'Unknown'}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {session.user_type} ·{' '}
                                        {session.ip_address}
                                    </p>
                                </div>
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    {new Date(
                                        session.last_activity * 1000,
                                    ).toLocaleTimeString()}
                                </span>
                            </div>
                        ))}
                        {/*
                          Two different empty states, deliberately.

                          "No active sessions" is a claim about the
                          platform; under a Redis or file session driver
                          it would be an untrue one, because the sessions
                          table is not where they live. On a security
                          screen that difference is the difference between
                          "the intruder logged out" and "we cannot see
                          them".
                        */}
                        {!sessionsTracked ? (
                            <p className="py-8 text-center text-sm text-amber-700 dark:text-amber-400">
                                Session tracking needs{' '}
                                <code>SESSION_DRIVER=database</code>. This
                                installation uses{' '}
                                <strong>{sessionDriver}</strong>, so active
                                sessions cannot be listed here.
                            </p>
                        ) : (
                            activeSessions.length === 0 && (
                                <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No active sessions.
                                </p>
                            )
                        )}
                    </div>
                </BiCard>
            </div>

            <BiModal
                show={blocking}
                onClose={() => setBlocking(false)}
                title="Block an IP address"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setBlocking(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton type="submit" form="block-ip-form">
                            Block
                        </BiButton>
                    </>
                }
            >
                <form
                    id="block-ip-form"
                    onSubmit={submitBlock}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            IP address
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={ipAddress}
                            onChange={(e) => setIpAddress(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Reason
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                        />
                    </div>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
