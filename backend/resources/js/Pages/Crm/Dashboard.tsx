import Card from '@/Components/Card';
import MonthlySalesBarChart from '@/Components/Charts/MonthlySalesBarChart';
import StatCard from '@/Components/StatCard';
import CrmLayout from '@/Layouts/CrmLayout';
import { formatCurrency } from '@/lib/currency';
import {
    CrmDashboardSummary,
    NewCustomersTrendPoint,
    TopCustomer,
} from '@/types/crm';
import {
    ExclamationTriangleIcon,
    SparklesIcon,
    StarIcon,
    UserGroupIcon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';

export default function CrmDashboard({
    summary,
    topCustomers,
    newCustomersTrend,
}: {
    summary: CrmDashboardSummary;
    topCustomers: TopCustomer[];
    newCustomersTrend: NewCustomersTrendPoint[];
}) {
    const trendData = newCustomersTrend.map((point) => ({
        label: point.label,
        amount: point.count,
    }));

    return (
        <CrmLayout title="Dashboard">
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<UsersIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Total Customers"
                    value={summary.total_customers}
                    delta={`${summary.active_customers} active`}
                    deltaTone="positive"
                />
                <StatCard
                    icon={<UserPlusIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="New This Month"
                    value={summary.new_customers_this_month}
                />
                <StatCard
                    icon={<StarIcon className="h-5 w-5" />}
                    iconClassName="bg-amber-600"
                    title="VIP Customers"
                    value={summary.vip_customers}
                />
                <StatCard
                    icon={<ExclamationTriangleIcon className="h-5 w-5" />}
                    iconClassName="bg-rose-600"
                    title="Outstanding Debts"
                    value={formatCurrency(summary.outstanding_debts)}
                />
            </div>

            <div className="grid gap-6 sm:grid-cols-2">
                <StatCard
                    icon={<SparklesIcon className="h-5 w-5" />}
                    iconClassName="bg-purple-600"
                    title="Total Loyalty Points Outstanding"
                    value={summary.total_loyalty_points}
                />
                <StatCard
                    icon={<UserGroupIcon className="h-5 w-5" />}
                    iconClassName="bg-blue-600"
                    title="Active Customers"
                    value={summary.active_customers}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card
                    title="New Customers"
                    description="Last 14 days"
                    className="lg:col-span-2"
                >
                    {trendData.some((p) => p.amount > 0) ? (
                        <MonthlySalesBarChart data={trendData} />
                    ) : (
                        <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No new customers in the last 14 days.
                        </p>
                    )}
                </Card>

                <Card title="Top Customers" description="By lifetime value">
                    {topCustomers.length > 0 ? (
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {topCustomers.map((customer) => (
                                <div
                                    key={customer.customer_id}
                                    className="flex items-center justify-between py-2.5 text-sm"
                                >
                                    <span className="text-gray-900 dark:text-gray-100">
                                        {customer.name}
                                    </span>
                                    <span className="text-gray-500 dark:text-gray-400">
                                        {formatCurrency(
                                            customer.lifetime_value,
                                        )}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No completed sales yet.
                        </p>
                    )}
                </Card>
            </div>
        </CrmLayout>
    );
}
