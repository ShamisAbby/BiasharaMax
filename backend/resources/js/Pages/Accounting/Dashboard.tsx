import Card from '@/Components/Card';
import ProfitTrendLineChart from '@/Components/Charts/ProfitTrendLineChart';
import StatCard from '@/Components/StatCard';
import AccountingLayout from '@/Layouts/AccountingLayout';
import { formatCurrency } from '@/lib/currency';
import { FinancialSummary, ProfitTrendPoint } from '@/types/accounting';
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    ClockIcon,
    CreditCardIcon,
    ExclamationTriangleIcon,
    ReceiptPercentIcon,
} from '@heroicons/react/24/outline';

export default function AccountingDashboard({
    summary,
    profitTrend,
}: {
    summary: FinancialSummary;
    profitTrend: ProfitTrendPoint[];
}) {
    return (
        <AccountingLayout title="Dashboard">
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<BanknotesIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="Cash Balance"
                    value={formatCurrency(summary.cash_balance)}
                />
                <StatCard
                    icon={<BuildingLibraryIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Bank Balance"
                    value={formatCurrency(summary.bank_balance)}
                />
                <StatCard
                    icon={<CreditCardIcon className="h-5 w-5" />}
                    iconClassName="bg-amber-600"
                    title="This Month's Revenue"
                    value={formatCurrency(summary.total_revenue)}
                />
                <StatCard
                    icon={<ReceiptPercentIcon className="h-5 w-5" />}
                    iconClassName="bg-rose-600"
                    title="This Month's Expenses"
                    value={formatCurrency(summary.total_expenses)}
                />
            </div>

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<BanknotesIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="Gross Profit"
                    value={formatCurrency(summary.gross_profit)}
                    deltaTone={
                        summary.gross_profit >= 0 ? 'positive' : 'negative'
                    }
                />
                <StatCard
                    icon={<BanknotesIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-700"
                    title="Net Profit"
                    value={formatCurrency(summary.net_profit)}
                    deltaTone={
                        summary.net_profit >= 0 ? 'positive' : 'negative'
                    }
                />
                <StatCard
                    icon={<ExclamationTriangleIcon className="h-5 w-5" />}
                    iconClassName="bg-orange-600"
                    title="Outstanding Debts (AR)"
                    value={formatCurrency(summary.outstanding_debts)}
                />
                <StatCard
                    icon={<ClockIcon className="h-5 w-5" />}
                    iconClassName="bg-gray-600"
                    title="Accounts Payable"
                    value={formatCurrency(summary.accounts_payable)}
                    delta={`${summary.pending_expenses_count} expense(s) awaiting approval`}
                    deltaTone="warning"
                />
            </div>

            <Card title="Revenue, Expenses & Profit" description="Last 14 days">
                {profitTrend.some((p) => p.revenue > 0 || p.expenses > 0) ? (
                    <div className="h-72">
                        <ProfitTrendLineChart data={profitTrend} />
                    </div>
                ) : (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No revenue or expenses recorded yet — your trend will
                        appear here.
                    </p>
                )}
            </Card>
        </AccountingLayout>
    );
}
