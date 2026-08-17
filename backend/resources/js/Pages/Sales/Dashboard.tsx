import Card from '@/Components/Card';
import MonthlySalesBarChart from '@/Components/Charts/MonthlySalesBarChart';
import PaymentMethodDonutChart from '@/Components/Charts/PaymentMethodDonutChart';
import StatCard from '@/Components/StatCard';
import SalesLayout from '@/Layouts/SalesLayout';
import { formatCurrency } from '@/lib/currency';
import { SalesDashboardSummary } from '@/types/sales';
import {
    BanknotesIcon,
    CreditCardIcon,
    ExclamationTriangleIcon,
    ShoppingCartIcon,
} from '@heroicons/react/24/outline';

interface RevenuePoint {
    label: string;
    amount: number;
}

interface TopProduct {
    product_name: string;
    quantity_sold: number;
    revenue: number;
}

interface PaymentMethodRow {
    payment_method: string;
    total: number;
    count: number;
}

export default function SalesDashboard({
    summary,
    revenueTrend,
    topSellingProducts,
    paymentMethodBreakdown,
}: {
    summary: SalesDashboardSummary;
    revenueTrend: RevenuePoint[];
    topSellingProducts: TopProduct[];
    paymentMethodBreakdown: PaymentMethodRow[];
}) {
    const totalPayments = paymentMethodBreakdown.reduce(
        (sum, row) => sum + row.total,
        0,
    );
    const paymentMethodChartData = paymentMethodBreakdown.map((row) => ({
        label: row.payment_method.replace('_', ' '),
        percentage:
            totalPayments > 0
                ? Math.round((row.total / totalPayments) * 100)
                : 0,
    }));

    return (
        <SalesLayout title="Dashboard">
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<ShoppingCartIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Today's Sales"
                    value={summary.today_sales_count}
                    delta={`${formatCurrency(summary.today_revenue)} revenue`}
                    deltaTone="positive"
                />
                <StatCard
                    icon={<BanknotesIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="This Month's Revenue"
                    value={formatCurrency(summary.month_revenue)}
                    delta={`${summary.month_sales_count} sales`}
                    deltaTone="positive"
                />
                <StatCard
                    icon={<CreditCardIcon className="h-5 w-5" />}
                    iconClassName="bg-amber-600"
                    title="Outstanding Credit"
                    value={formatCurrency(summary.outstanding_credit)}
                />
                <StatCard
                    icon={<ExclamationTriangleIcon className="h-5 w-5" />}
                    iconClassName="bg-rose-600"
                    title="Unpaid Sales"
                    value={summary.unpaid_sales_count}
                    deltaTone="warning"
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card
                    title="Revenue Trend"
                    description="Last 14 days"
                    className="lg:col-span-2"
                >
                    {revenueTrend.some((p) => p.amount > 0) ? (
                        <MonthlySalesBarChart data={revenueTrend} />
                    ) : (
                        <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No sales recorded yet — revenue will appear here
                            once you make your first sale.
                        </p>
                    )}
                </Card>

                <Card title="Payment Methods">
                    {paymentMethodChartData.length > 0 ? (
                        <PaymentMethodDonutChart
                            data={paymentMethodChartData}
                        />
                    ) : (
                        <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No payments recorded yet.
                        </p>
                    )}
                </Card>
            </div>

            <Card title="Top Selling Products">
                {topSellingProducts.length > 0 ? (
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {topSellingProducts.map((product) => (
                            <div
                                key={product.product_name}
                                className="flex items-center justify-between py-2.5 text-sm"
                            >
                                <span className="text-gray-900 dark:text-gray-100">
                                    {product.product_name}
                                </span>
                                <span className="text-gray-500 dark:text-gray-400">
                                    {product.quantity_sold} sold ·{' '}
                                    {formatCurrency(product.revenue)}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        No sales yet — top products will appear here once you
                        start selling.
                    </p>
                )}
            </Card>
        </SalesLayout>
    );
}
