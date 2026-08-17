import BiBadge from '@/Components/Bi/BiBadge';
import BiCard from '@/Components/Bi/BiCard';
import BiChart from '@/Components/Bi/BiChart';
import BiStatsCard from '@/Components/Bi/BiStatsCard';
import PlatformFinanceLayout from '@/Layouts/PlatformFinanceLayout';
import { formatCurrency } from '@/lib/currency';
import {
    BanknotesIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    ReceiptPercentIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';

interface TransactionRow {
    id: string;
    reference_number: string;
    business: { id: string; name: string } | null;
    gateway: { id: string; name: string } | null;
    amount: string;
    currency: string;
    status: string;
    created_at: string;
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    pending: 'warning',
    processing: 'info',
    successful: 'success',
    failed: 'danger',
    cancelled: 'neutral',
    refunded: 'neutral',
    partially_refunded: 'warning',
    expired: 'neutral',
};

export default function FinanceDashboard({
    revenue,
    transactionCounts,
    commission,
    monthlyGrowth,
    recentTransactions,
    failedTransactions,
    pendingTransactions,
    topBusinesses,
    topPaymentMethods,
    gatewayPerformance,
}: {
    revenue: {
        total: number;
        today: number;
        this_month: number;
        this_year: number;
    };
    transactionCounts: {
        pending: number;
        successful: number;
        failed: number;
        refunded: number;
        chargebacks: number;
    };
    commission: { commission: number; tax_collected: number; fees: number };
    monthlyGrowth: { label: string; amount: number }[];
    recentTransactions: { data: TransactionRow[] };
    failedTransactions: { data: TransactionRow[] };
    pendingTransactions: { data: TransactionRow[] };
    topBusinesses: {
        business_id: string;
        business_name: string | null;
        total: number;
    }[];
    topPaymentMethods: {
        payment_method: string;
        total: number;
        count: number;
    }[];
    gatewayPerformance: {
        gateway_id: string;
        gateway_name: string;
        total: number;
        successful: number;
        failed: number;
        success_rate: number;
    }[];
}) {
    const recent = Array.isArray(recentTransactions)
        ? recentTransactions
        : recentTransactions.data;
    const failed = Array.isArray(failedTransactions)
        ? failedTransactions
        : failedTransactions.data;
    const pending = Array.isArray(pendingTransactions)
        ? pendingTransactions
        : pendingTransactions.data;

    return (
        <PlatformFinanceLayout title="Dashboard">
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
                        title="Today's Revenue"
                        value={revenue.today}
                        formatter={(v) => formatCurrency(v)}
                    />
                    <BiStatsCard
                        icon={<BanknotesIcon className="h-5 w-5" />}
                        iconClassName="bg-blue-600"
                        title="Monthly Revenue"
                        value={revenue.this_month}
                        formatter={(v) => formatCurrency(v)}
                    />
                    <BiStatsCard
                        icon={<BanknotesIcon className="h-5 w-5" />}
                        iconClassName="bg-purple-600"
                        title="Annual Revenue"
                        value={revenue.this_year}
                        formatter={(v) => formatCurrency(v)}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <BiStatsCard
                        icon={<ClockIcon className="h-5 w-5" />}
                        iconClassName="bg-amber-600"
                        title="Pending"
                        value={transactionCounts.pending}
                    />
                    <BiStatsCard
                        icon={<CheckCircleIcon className="h-5 w-5" />}
                        iconClassName="bg-emerald-600"
                        title="Successful"
                        value={transactionCounts.successful}
                    />
                    <BiStatsCard
                        icon={<XCircleIcon className="h-5 w-5" />}
                        iconClassName="bg-red-600"
                        title="Failed"
                        value={transactionCounts.failed}
                    />
                    <BiStatsCard
                        icon={<ReceiptPercentIcon className="h-5 w-5" />}
                        iconClassName="bg-gray-600"
                        title="Refunded"
                        value={transactionCounts.refunded}
                    />
                    <BiStatsCard
                        icon={<ExclamationTriangleIcon className="h-5 w-5" />}
                        iconClassName="bg-orange-600"
                        title="Chargebacks"
                        value={transactionCounts.chargebacks}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <BiStatsCard
                        icon={<ReceiptPercentIcon className="h-5 w-5" />}
                        iconClassName="bg-indigo-600"
                        title="Platform Commission"
                        value={commission.commission}
                        formatter={(v) => formatCurrency(v)}
                    />
                    <BiStatsCard
                        icon={<ReceiptPercentIcon className="h-5 w-5" />}
                        iconClassName="bg-blue-600"
                        title="Taxes Collected"
                        value={commission.tax_collected}
                        formatter={(v) => formatCurrency(v)}
                    />
                    <BiStatsCard
                        icon={<ReceiptPercentIcon className="h-5 w-5" />}
                        iconClassName="bg-gray-600"
                        title="Gateway Fees"
                        value={commission.fees}
                        formatter={(v) => formatCurrency(v)}
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
                            labels={monthlyGrowth.map((p) => p.label)}
                            datasets={[
                                {
                                    label: 'Revenue',
                                    data: monthlyGrowth.map((p) => p.amount),
                                },
                            ]}
                            showLegend={false}
                        />
                    </BiCard>

                    <BiCard title="Top Payment Methods">
                        {topPaymentMethods.length > 0 ? (
                            <BiChart
                                type="doughnut"
                                labels={topPaymentMethods.map(
                                    (m) => m.payment_method,
                                )}
                                datasets={[
                                    {
                                        label: 'Revenue',
                                        data: topPaymentMethods.map(
                                            (m) => m.total,
                                        ),
                                    },
                                ]}
                            />
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No payment data yet.
                            </p>
                        )}
                    </BiCard>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BiCard
                        title="Top Businesses"
                        description="By total revenue"
                    >
                        {topBusinesses.length > 0 ? (
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {topBusinesses.map((b) => (
                                    <div
                                        key={b.business_id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <span className="font-medium text-gray-900 dark:text-gray-100">
                                            {b.business_name ?? '—'}
                                        </span>
                                        <span className="text-gray-500 dark:text-gray-400">
                                            {formatCurrency(b.total)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No revenue yet.
                            </p>
                        )}
                    </BiCard>

                    <BiCard title="Gateway Performance">
                        {gatewayPerformance.length > 0 ? (
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {gatewayPerformance.map((g) => (
                                    <div
                                        key={g.gateway_id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <span className="font-medium text-gray-900 dark:text-gray-100">
                                            {g.gateway_name}
                                        </span>
                                        <span className="text-gray-500 dark:text-gray-400">
                                            {g.success_rate}% success
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No gateways yet.
                            </p>
                        )}
                    </BiCard>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BiCard title="Recent Transactions">
                        <TransactionList
                            rows={recent}
                            emptyMessage="No transactions yet."
                        />
                        <Link
                            href={route('platform.finance.payments.index')}
                            className="mt-4 inline-block text-sm font-medium text-indigo-600 hover:underline"
                        >
                            View all payments →
                        </Link>
                    </BiCard>

                    <BiCard title="Failed Payments">
                        <TransactionList
                            rows={failed}
                            emptyMessage="No failed payments."
                        />
                    </BiCard>
                </div>

                <BiCard title="Pending Payments">
                    <TransactionList
                        rows={pending}
                        emptyMessage="No pending payments."
                    />
                </BiCard>
            </div>
        </PlatformFinanceLayout>
    );
}

function TransactionList({
    rows,
    emptyMessage,
}: {
    rows: TransactionRow[];
    emptyMessage: string;
}) {
    if (rows.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                {emptyMessage}
            </p>
        );
    }

    return (
        <div className="divide-y divide-gray-100 dark:divide-gray-700">
            {rows.map((t) => (
                <div
                    key={t.id}
                    className="flex items-center justify-between py-2 text-sm"
                >
                    <div>
                        <p className="font-medium text-gray-900 dark:text-gray-100">
                            {t.business?.name ?? '—'}
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {t.reference_number}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-gray-700 dark:text-gray-300">
                            {formatCurrency(Number(t.amount))}
                        </span>
                        <BiBadge
                            variant={STATUS_VARIANT[t.status] ?? 'neutral'}
                        >
                            {t.status}
                        </BiBadge>
                    </div>
                </div>
            ))}
        </div>
    );
}
