import Card from '@/Components/Card';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Account } from '@/types/finance';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ReportNav } from './TrialBalance';

interface AccountAmount {
    account: Account;
    amount: string;
}

interface ProfitAndLossReport {
    from_date: string;
    to_date: string;
    revenue_accounts: AccountAmount[];
    total_revenue: string;
    expense_accounts: AccountAmount[];
    total_expenses: string;
    net_profit: string;
}

export default function ProfitAndLoss({
    report,
}: {
    report: ProfitAndLossReport;
}) {
    const [from, setFrom] = useState(report.from_date);
    const [to, setTo] = useState(report.to_date);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('finance.reports.profit-and-loss'),
            { from, to },
            { preserveState: true },
        );
    };

    const Row = ({
        label,
        value,
        bold = false,
        indent = false,
        accountId,
    }: {
        label: string;
        value: string;
        bold?: boolean;
        indent?: boolean;
        accountId?: string;
    }) => (
        <div
            className={`flex items-center justify-between py-2 ${bold ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300'}`}
        >
            <span className={indent ? 'pl-4' : ''}>
                {accountId ? (
                    <Link
                        href={route('finance.ledger.show', accountId)}
                        className="text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        {label}
                    </Link>
                ) : (
                    label
                )}
            </span>
            <span className="tabular-nums">
                {formatCurrency(parseFloat(value))}
            </span>
        </div>
    );

    const netProfit = parseFloat(report.net_profit);

    return (
        <FinanceLayout title="Profit & Loss">
            <ReportNav active="profit-and-loss" />

            <div className="rounded-md bg-blue-50 p-3 text-xs text-gray-500 dark:bg-blue-900/20 dark:text-gray-400">
                This is the GL-derived Profit & Loss. Minor differences from the
                Accounting Dashboard P&L are expected due to timing and
                rounding.
            </div>

            <div className="flex flex-wrap items-end justify-between gap-3">
                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div>
                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                            From
                        </label>
                        <TextInput
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                            To
                        </label>
                        <TextInput
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </div>
                    <SecondaryButton type="submit">Apply</SecondaryButton>
                </form>
                <a
                    href={route('finance.reports.profit-and-loss.pdf', {
                        from,
                        to,
                    })}
                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    target="_blank"
                    rel="noreferrer"
                >
                    <ArrowDownTrayIcon className="h-4 w-4" />
                    Download PDF
                </a>
            </div>

            <Card
                title="Profit & Loss Statement"
                description={`${report.from_date} to ${report.to_date}`}
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    <div className="pb-4">
                        <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            Revenue
                        </p>
                        {report.revenue_accounts.length > 0 ? (
                            report.revenue_accounts.map(
                                ({ account, amount }) => (
                                    <Row
                                        key={account.id}
                                        label={account.name}
                                        value={amount}
                                        indent
                                        accountId={account.id}
                                    />
                                ),
                            )
                        ) : (
                            <p className="py-2 pl-4 text-sm text-gray-500 dark:text-gray-400">
                                No revenue posted in this period.
                            </p>
                        )}
                        <Row
                            label="Total Revenue"
                            value={report.total_revenue}
                            bold
                        />
                    </div>

                    <div className="py-4">
                        <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            Expenses
                        </p>
                        {report.expense_accounts.length > 0 ? (
                            report.expense_accounts.map(
                                ({ account, amount }) => (
                                    <Row
                                        key={account.id}
                                        label={account.name}
                                        value={amount}
                                        indent
                                        accountId={account.id}
                                    />
                                ),
                            )
                        ) : (
                            <p className="py-2 pl-4 text-sm text-gray-500 dark:text-gray-400">
                                No expenses posted in this period.
                            </p>
                        )}
                        <Row
                            label="Total Expenses"
                            value={report.total_expenses}
                            bold
                        />
                    </div>

                    <div className="pt-4">
                        <div
                            className={`flex items-center justify-between text-lg font-bold ${netProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}
                        >
                            <span>Net Profit</span>
                            <span className="tabular-nums">
                                {formatCurrency(netProfit)}
                            </span>
                        </div>
                    </div>
                </div>
            </Card>
        </FinanceLayout>
    );
}
