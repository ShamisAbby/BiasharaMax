import BiChart from '@/Components/Bi/BiChart';
import BiEmptyState from '@/Components/Bi/BiEmptyState';
import BiKpiCard from '@/Components/Bi/BiKpiCard';
import Card from '@/Components/Card';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    ChartBarIcon,
    CreditCardIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    ScaleIcon,
    UserGroupIcon,
} from '@heroicons/react/24/outline';

interface PlTrendRow {
    label: string;
    revenue: number;
    expenses: number;
    profit: number;
}

interface Alert {
    type: 'warning' | 'info';
    message: string;
}

interface FinanceSummary {
    cash: { value: number };
    bank: { value: number };
    accounts_receivable: { value: number };
    accounts_payable: { value: number };
    working_capital: { value: number };
    pl_trend: PlTrendRow[];
    alerts: Alert[];
}

export default function FinanceDashboard({
    summary,
}: {
    summary: FinanceSummary | null;
}) {
    if (!summary) {
        return (
            <FinanceLayout title="Dashboard">
                <BiEmptyState
                    title="GL data not available"
                    description="Your Chart of Accounts has not been seeded yet. Run finance:seed-chart-of-accounts and finance:seed-opening-balances to initialise the General Ledger for this business."
                    icon={<ChartBarIcon className="h-10 w-10" />}
                />
            </FinanceLayout>
        );
    }

    const fmt = (v: number) => formatCurrency(v);
    const wc = summary.working_capital.value;

    return (
        <FinanceLayout title="Dashboard">
            {summary.alerts && summary.alerts.length > 0 && (
                <div className="space-y-2">
                    {summary.alerts.map((alert, i) => (
                        <div
                            key={i}
                            className={`flex items-start gap-3 rounded-lg px-4 py-3 text-sm ${
                                alert.type === 'warning'
                                    ? 'bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300'
                                    : 'bg-blue-50 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300'
                            }`}
                        >
                            {alert.type === 'warning' ? (
                                <ExclamationTriangleIcon className="mt-0.5 h-4 w-4 shrink-0" />
                            ) : (
                                <InformationCircleIcon className="mt-0.5 h-4 w-4 shrink-0" />
                            )}
                            {alert.message}
                        </div>
                    ))}
                </div>
            )}

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <BiKpiCard
                    icon={<BanknotesIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="Cash"
                    metric={summary.cash}
                    formatter={fmt}
                    href={route('finance.ledger.index')}
                />
                <BiKpiCard
                    icon={<BuildingLibraryIcon className="h-5 w-5" />}
                    iconClassName="bg-blue-600"
                    title="Bank"
                    metric={summary.bank}
                    formatter={fmt}
                    href={route('finance.ledger.index')}
                />
                <BiKpiCard
                    icon={<UserGroupIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Accounts Receivable"
                    metric={summary.accounts_receivable}
                    formatter={fmt}
                    href={route('finance.ledger.index')}
                />
                <BiKpiCard
                    icon={<CreditCardIcon className="h-5 w-5" />}
                    iconClassName="bg-orange-600"
                    title="Accounts Payable"
                    metric={summary.accounts_payable}
                    formatter={fmt}
                    invertTone
                    href={route('finance.ledger.index')}
                />
                <BiKpiCard
                    icon={<ScaleIcon className="h-5 w-5" />}
                    iconClassName={wc >= 0 ? 'bg-teal-600' : 'bg-red-600'}
                    title="Working Capital"
                    metric={summary.working_capital}
                    formatter={fmt}
                    invertTone={wc < 0}
                    href={route('finance.reports.balance-sheet')}
                />
            </div>

            {summary.pl_trend.length > 0 && (
                <Card
                    title="Profit & Loss Trend"
                    description="Last 6 months — GL-derived figures"
                >
                    <BiChart
                        type="bar"
                        height={280}
                        labels={summary.pl_trend.map((r) => r.label)}
                        datasets={[
                            {
                                label: 'Revenue',
                                data: summary.pl_trend.map((r) => r.revenue),
                                color: '#4F46E5',
                            },
                            {
                                label: 'Expenses',
                                data: summary.pl_trend.map((r) => r.expenses),
                                color: '#F97316',
                            },
                            {
                                label: 'Net Profit',
                                data: summary.pl_trend.map((r) => r.profit),
                                color: '#10B981',
                            },
                        ]}
                        showLegend
                    />
                </Card>
            )}

            <div className="grid gap-4 sm:grid-cols-3">
                <a
                    href={route('finance.reports.profit-and-loss')}
                    className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 hover:shadow-md dark:bg-gray-800 dark:ring-gray-700"
                >
                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Profit & Loss
                    </p>
                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        GL-derived income statement
                    </p>
                </a>
                <a
                    href={route('finance.reports.balance-sheet')}
                    className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 hover:shadow-md dark:bg-gray-800 dark:ring-gray-700"
                >
                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Balance Sheet
                    </p>
                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Assets, liabilities and equity
                    </p>
                </a>
                <a
                    href={route('finance.reports.cash-flow')}
                    className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 hover:shadow-md dark:bg-gray-800 dark:ring-gray-700"
                >
                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Cash Flow
                    </p>
                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Operating activities (indirect method)
                    </p>
                </a>
            </div>
        </FinanceLayout>
    );
}
