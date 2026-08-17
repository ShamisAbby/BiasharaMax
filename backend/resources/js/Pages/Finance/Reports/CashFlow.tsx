import Card from '@/Components/Card';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ReportNav } from './TrialBalance';

interface CashFlowReport {
    from_date: string;
    to_date: string;
    net_income: string;
    changes_in_working_capital: {
        accounts_receivable: string;
        inventory: string;
        accounts_payable: string;
    };
    net_cash_from_operating_activities: string;
    net_cash_from_investing_activities: string;
    net_cash_from_financing_activities: string;
    net_change_in_cash: string;
    cash_at_start: string;
    cash_at_end: string;
    actual_change_in_cash: string;
}

export default function CashFlow({ report }: { report: CashFlowReport }) {
    const [from, setFrom] = useState(report.from_date);
    const [to, setTo] = useState(report.to_date);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('finance.reports.cash-flow'),
            { from, to },
            { preserveState: true },
        );
    };

    const Row = ({
        label,
        value,
        bold = false,
        indent = false,
    }: {
        label: string;
        value: string;
        bold?: boolean;
        indent?: boolean;
    }) => {
        const n = parseFloat(value);

        return (
            <div
                className={`flex items-center justify-between py-2 ${bold ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300'}`}
            >
                <span className={indent ? 'pl-4' : ''}>{label}</span>
                <span
                    className={`tabular-nums ${n < 0 ? 'text-red-600 dark:text-red-400' : ''}`}
                >
                    {n >= 0
                        ? formatCurrency(n)
                        : `(${formatCurrency(Math.abs(n))})`}
                </span>
            </div>
        );
    };

    const computed = parseFloat(report.net_cash_from_operating_activities);
    const actual = parseFloat(report.actual_change_in_cash);
    const divergence = Math.abs(computed - actual) > 0.01;

    return (
        <FinanceLayout title="Cash Flow">
            <ReportNav active="cash-flow" />

            <div className="rounded-md bg-blue-50 p-3 text-xs text-gray-500 dark:bg-blue-900/20 dark:text-gray-400">
                Phase 1 models operating activities only. Investing and
                financing activities will be added in a future phase.
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
                    href={route('finance.reports.cash-flow.pdf', { from, to })}
                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    target="_blank"
                    rel="noreferrer"
                >
                    <ArrowDownTrayIcon className="h-4 w-4" />
                    Download PDF
                </a>
            </div>

            <Card
                title="Cash Flow Statement"
                description={`${report.from_date} to ${report.to_date} (indirect method)`}
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    <div className="pb-4">
                        <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            Operating Activities
                        </p>
                        <Row
                            label="Net Income"
                            value={report.net_income}
                            indent
                        />
                        <p className="mb-1 pl-4 text-xs font-medium text-gray-400">
                            Changes in Working Capital
                        </p>
                        <Row
                            label="Accounts Receivable"
                            value={
                                report.changes_in_working_capital
                                    .accounts_receivable
                            }
                            indent
                        />
                        <Row
                            label="Inventory"
                            value={report.changes_in_working_capital.inventory}
                            indent
                        />
                        <Row
                            label="Accounts Payable"
                            value={
                                report.changes_in_working_capital
                                    .accounts_payable
                            }
                            indent
                        />
                        <Row
                            label="Net Cash from Operating Activities"
                            value={report.net_cash_from_operating_activities}
                            bold
                        />
                    </div>

                    <div className="py-4">
                        <Row
                            label="Net Cash from Investing Activities"
                            value={report.net_cash_from_investing_activities}
                            bold
                        />
                        <Row
                            label="Net Cash from Financing Activities"
                            value={report.net_cash_from_financing_activities}
                            bold
                        />
                    </div>

                    <div className="py-4">
                        <Row
                            label="Net Change in Cash"
                            value={report.net_change_in_cash}
                            bold
                        />
                        <Row
                            label="Cash & Bank at Start of Period"
                            value={report.cash_at_start}
                        />
                        <Row
                            label="Cash & Bank at End of Period"
                            value={report.cash_at_end}
                            bold
                        />
                    </div>

                    {divergence && (
                        <div className="pt-4">
                            <div className="rounded-md bg-yellow-50 p-3 text-xs text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-300">
                                Note: computed net change (
                                {formatCurrency(computed)}) differs from actual
                                GL cash change ({formatCurrency(actual)}). This
                                is normal if an Opening Balance entry moved cash
                                directly against equity rather than through the
                                income statement.
                            </div>
                        </div>
                    )}
                </div>
            </Card>
        </FinanceLayout>
    );
}
