import BiBadge from '@/Components/Bi/BiBadge';
import BiCard from '@/Components/Bi/BiCard';
import BiChart from '@/Components/Bi/BiChart';
import BiStatsCard from '@/Components/Bi/BiStatsCard';
import PlatformLicensesLayout from '@/Layouts/PlatformLicensesLayout';
import {
    ExclamationTriangleIcon,
    KeyIcon,
    NoSymbolIcon,
    ShieldCheckIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';

interface Overview {
    total: number;
    active: number;
    suspended: number;
    revoked: number;
    expiring_in_30_days: number;
}

interface ExpiringLicense {
    id: string;
    license_key: string;
    business_name: string;
    expires_at: string;
}

export default function LicensesDashboard({
    overview,
    byType,
    expiringSoon,
}: {
    overview: Overview;
    byType: Array<{ label: string; count: number }>;
    expiringSoon: ExpiringLicense[];
}) {
    return (
        <PlatformLicensesLayout title="Dashboard">
            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <BiStatsCard
                        icon={<KeyIcon className="h-5 w-5" />}
                        iconClassName="bg-indigo-600"
                        title="Total Licenses"
                        value={overview.total}
                    />
                    <BiStatsCard
                        icon={<ShieldCheckIcon className="h-5 w-5" />}
                        iconClassName="bg-emerald-600"
                        title="Active"
                        value={overview.active}
                    />
                    <BiStatsCard
                        icon={<ExclamationTriangleIcon className="h-5 w-5" />}
                        iconClassName="bg-amber-600"
                        title="Suspended"
                        value={overview.suspended}
                    />
                    <BiStatsCard
                        icon={<NoSymbolIcon className="h-5 w-5" />}
                        iconClassName="bg-red-600"
                        title="Revoked"
                        value={overview.revoked}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard title="Licenses by Type">
                        {byType.length > 0 ? (
                            <BiChart
                                type="doughnut"
                                labels={byType.map((p) => p.label)}
                                datasets={[
                                    {
                                        label: 'Licenses',
                                        data: byType.map((p) => p.count),
                                    },
                                ]}
                            />
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No licenses yet.
                            </p>
                        )}
                    </BiCard>

                    <BiCard
                        title="Expiring Within 30 Days"
                        className="lg:col-span-2"
                    >
                        {expiringSoon.length > 0 ? (
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {expiringSoon.map((license) => (
                                    <Link
                                        key={license.id}
                                        href={route(
                                            'platform.licenses.show',
                                            license.id,
                                        )}
                                        className="flex items-center justify-between py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-900/30"
                                    >
                                        <div>
                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                {license.business_name}
                                            </p>
                                            <p className="text-gray-500 dark:text-gray-400">
                                                {license.license_key}
                                            </p>
                                        </div>
                                        <BiBadge variant="warning">
                                            {new Date(
                                                license.expires_at,
                                            ).toLocaleDateString()}
                                        </BiBadge>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No licenses expiring soon.
                            </p>
                        )}
                    </BiCard>
                </div>
            </div>
        </PlatformLicensesLayout>
    );
}
