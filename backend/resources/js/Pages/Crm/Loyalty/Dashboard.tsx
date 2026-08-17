import Card from '@/Components/Card';
import StatCard from '@/Components/StatCard';
import CrmLayout from '@/Layouts/CrmLayout';
import {
    LoyaltyDashboardSummary,
    LoyaltyTierDistributionPoint,
    TopLoyalCustomer,
} from '@/types/crm';
import {
    ArrowDownCircleIcon,
    ArrowUpCircleIcon,
    GiftIcon,
    SparklesIcon,
    StarIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';

export default function LoyaltyDashboard({
    summary,
    topLoyalCustomers,
    tierDistribution,
}: {
    summary: LoyaltyDashboardSummary;
    topLoyalCustomers: TopLoyalCustomer[];
    tierDistribution: LoyaltyTierDistributionPoint[];
}) {
    return (
        <CrmLayout title="Loyalty Program">
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<UsersIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Loyalty Members"
                    value={summary.total_members}
                    delta={`${summary.active_members} active`}
                    deltaTone="positive"
                />
                <StatCard
                    icon={<StarIcon className="h-5 w-5" />}
                    iconClassName="bg-amber-600"
                    title="VIP Customers"
                    value={summary.vip_customers}
                />
                <StatCard
                    icon={<ArrowUpCircleIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="Points Issued"
                    value={summary.points_issued}
                    delta="This month"
                />
                <StatCard
                    icon={<ArrowDownCircleIcon className="h-5 w-5" />}
                    iconClassName="bg-rose-600"
                    title="Points Redeemed"
                    value={summary.points_redeemed}
                    delta="This month"
                />
            </div>

            <div className="grid gap-6 sm:grid-cols-2">
                <StatCard
                    icon={<GiftIcon className="h-5 w-5" />}
                    iconClassName="bg-purple-600"
                    title="Reward Redemptions"
                    value={summary.reward_redemptions_count}
                    delta="This month"
                />
                <StatCard
                    icon={<SparklesIcon className="h-5 w-5" />}
                    iconClassName="bg-blue-600"
                    title="Points Outstanding"
                    value={summary.points_outstanding}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card
                    title="Top Loyal Customers"
                    description="By current points balance"
                    className="lg:col-span-2"
                >
                    {topLoyalCustomers.length > 0 ? (
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {topLoyalCustomers.map((customer) => (
                                <div
                                    key={customer.customer_id}
                                    className="flex items-center justify-between py-2.5 text-sm"
                                >
                                    <div>
                                        <span className="text-gray-900 dark:text-gray-100">
                                            {customer.name}
                                        </span>
                                        {customer.tier && (
                                            <span className="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                {customer.tier}
                                            </span>
                                        )}
                                    </div>
                                    <span className="font-medium text-gray-700 dark:text-gray-300">
                                        {customer.loyalty_points} pts
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No loyalty activity yet.
                        </p>
                    )}
                </Card>

                <Card
                    title="Tier Distribution"
                    description="Customers per tier"
                >
                    {tierDistribution.length > 0 ? (
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {tierDistribution.map((point) => (
                                <div
                                    key={point.tier}
                                    className="flex items-center justify-between py-2.5 text-sm"
                                >
                                    <span className="text-gray-900 dark:text-gray-100">
                                        {point.tier}
                                    </span>
                                    <span className="text-gray-500 dark:text-gray-400">
                                        {point.customers_count}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No customers assigned to a tier yet.
                        </p>
                    )}
                </Card>
            </div>
        </CrmLayout>
    );
}
